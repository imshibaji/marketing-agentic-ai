<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\AuthService;
use MarketingAgent\Service\SmtpService;
use MarketingAgent\Service\WhatsAppService;
use MarketingAgent\Service\SmsService;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST requests are allowed.");
    }

    // Check Authentication
    $user = AuthService::getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception("Invalid JSON request body.");
    }

    $leadId = $input['lead_id'] ?? null;
    $type = $input['type'] ?? ''; // email, whatsapp, or sms

    if (!$leadId || !in_array($type, ['email', 'whatsapp', 'sms'])) {
        throw new Exception("Missing or invalid lead_id or type.");
    }

    $db = new DatabaseService();
    
    // Fetch Lead
    $lead = $db->getLead((int)$leadId);
    if (!$lead) {
        throw new Exception("Lead not found.");
    }

    // Verify permissions and set email subject
    $subject = "Business Outreach";
    if ($lead['campaign_id'] !== null) {
        $campaign = $db->getCampaign((int)$lead['campaign_id'], (int)$user['id'], $user['role']);
        if (!$campaign) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden. You do not have permission to access this lead campaign.']);
            exit;
        }
        $subject = "Outreach regarding " . $campaign['title'];
    } else {
        if ($user['role'] !== 'admin' && (int)$lead['user_id'] !== (int)$user['id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden. Access denied to this lead.']);
            exit;
        }
    }
    
    // Check and Enforce Quotas
    $userDetails = $db->getUserById((int)$user['id']);
    if ($userDetails && $userDetails['role'] !== 'admin') {
        if ($type === 'email') {
            if ($userDetails['plan_email'] !== -1 && $userDetails['email_usage'] >= $userDetails['plan_email']) {
                throw new Exception("Email outreach quota exceeded. You have used {$userDetails['email_usage']} of {$userDetails['plan_email']} allowed emails under your current plan. Please contact an administrator.");
            }
        } else if ($type === 'whatsapp') {
            if ($userDetails['plan_whatsapp'] !== -1 && $userDetails['whatsapp_usage'] >= $userDetails['plan_whatsapp']) {
                throw new Exception("WhatsApp outreach quota exceeded. You have used {$userDetails['whatsapp_usage']} of {$userDetails['plan_whatsapp']} allowed messages under your current plan. Please contact an administrator.");
            }
        } else if ($type === 'sms') {
            if ($userDetails['plan_sms'] !== -1 && $userDetails['sms_usage'] >= $userDetails['plan_sms']) {
                throw new Exception("SMS outreach quota exceeded. You have used {$userDetails['sms_usage']} of {$userDetails['plan_sms']} allowed SMS messages under your current plan. Please contact an administrator.");
            }
        }
    }

    // Fetch Settings
    $settings = $db->getSettings();
    $statusText = '';

    if ($type === 'email') {
        $toEmail = $lead['email'];
        // Keep $subject set above
        $body = $lead['email_draft'];

        if (empty($toEmail)) {
            throw new Exception("Recipient email is empty.");
        }

        $smtpHost = $settings['smtp_host'] ?? 'mock';
        $smtpPort = (int)($settings['smtp_port'] ?? 587);
        $smtpUser = $settings['smtp_user'] ?? '';
        $smtpPass = $settings['smtp_pass'] ?? '';
        $smtpFromEmail = $settings['smtp_from_email'] ?? 'outreach@example.com';
        $smtpFromName = $settings['smtp_from_name'] ?? 'Outreach Team';

        if ($smtpHost === 'mock' || empty($smtpHost)) {
            // Mock sending
            $statusText = "[SIMULATED EMAIL SENT] to {$toEmail} via Mock SMTP Host.";
        } else {
            // Real sending
            SmtpService::send(
                $smtpHost,
                $smtpPort,
                $smtpUser,
                $smtpPass,
                $smtpFromEmail,
                $smtpFromName,
                $toEmail,
                $subject,
                $body
            );
            $statusText = "Email outreach sent successfully to {$toEmail}!";
        }
        
        // Increment Email Usage
        if ($userDetails) {
            $db->incrementEmailUsage((int)$user['id']);
        }
    } else if ($type === 'whatsapp') {
        // WhatsApp Outreach
        $toPhone = $lead['whatsapp'];
        $body = $lead['whatsapp_draft'];

        if (empty($toPhone)) {
            throw new Exception("Recipient WhatsApp number is empty.");
        }

        $waToken = $settings['whatsapp_token'] ?? 'mock';
        $waPhoneId = $settings['whatsapp_phone_id'] ?? '';

        if ($waToken === 'mock' || empty($waToken) || empty($waPhoneId)) {
            // Mock sending
            $statusText = "[SIMULATED WHATSAPP SENT] to {$toPhone} via Mock WhatsApp Gate.";
        } else {
            // Real sending
            WhatsAppService::send($waToken, $waPhoneId, $toPhone, $body);
            $statusText = "WhatsApp outreach sent successfully to {$toPhone}!";
        }

        // Increment WhatsApp Usage
        if ($userDetails) {
            $db->incrementWhatsappUsage((int)$user['id']);
        }
    } else if ($type === 'sms') {
        // SMS Outreach
        $toPhone = $lead['mobile'] ?? $lead['whatsapp'] ?? '';
        $body = $lead['sms_draft'] ?? '';

        if (empty($toPhone)) {
            throw new Exception("Recipient mobile/phone number is empty.");
        }
        if (empty($body)) {
            throw new Exception("SMS draft outreach message is empty.");
        }

        $smsService = new SmsService($db);
        $smsService->sendSms($toPhone, $body);

        if (!$smsService->isConfigured()) {
            $statusText = "[SIMULATED SMS SENT] to {$toPhone} via Mock SMS Gateway.";
        } else {
            $statusText = "SMS outreach sent successfully to {$toPhone}!";
        }

        // Increment SMS Usage
        if ($userDetails) {
            $db->incrementSmsUsage((int)$user['id']);
        }
    }

    // Update lead status to OUTREACHED
    $db->updateLeadStatus((int)$leadId, 'OUTREACHED');

    // Log activity
    $db->logActivity((int)$user['id'], 'SEND_OUTREACH', "Sent " . strtoupper($type) . " outreach to lead '" . $lead['company_name'] . "'");

    echo json_encode([
        'success' => true,
        'message' => $statusText
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
