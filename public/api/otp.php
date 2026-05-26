<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

require_once __DIR__ . '/../../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\AuthService;
use MarketingAgent\Service\SmtpService;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST requests are allowed.");
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception("Invalid JSON request body.");
    }

    $action = $input['action'] ?? '';
    $email  = trim($input['email'] ?? '');

    if (empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email address is required.']);
        exit;
    }

    $db = new DatabaseService();

    if ($action === 'send') {
        // Look up user by email
        $user = $db->getUserByEmail($email);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'No user registered with this email.']);
            exit;
        }

        // Generate 6-digit OTP
        $otp = (string)rand(100000, 999999);
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes

        $db->createOtp($email, $otp, $expiresAt);

        // Fetch settings for SMTP
        $settings = $db->getSettings();
        $smtpHost = $settings['smtp_host'] ?? 'mock';
        $smtpPort = (int)($settings['smtp_port'] ?? 587);
        $smtpUser = $settings['smtp_user'] ?? '';
        $smtpPass = $settings['smtp_pass'] ?? '';
        $smtpFromEmail = $settings['smtp_from_email'] ?? 'outreach@example.com';
        $smtpFromName = $settings['smtp_from_name'] ?? 'Outreach Team';

        $isMock = ($smtpHost === 'mock' || empty($smtpHost));
        $sentSuccessfully = false;
        $errorMsg = '';

        if (!$isMock) {
            try {
                $subject = "Your Login OTP - " . ($settings['app_name'] ?? 'Marketing CRM');
                $body = "Hello {$user['username']},\n\nYour one-time password (OTP) for login is: {$otp}\n\nThis OTP is valid for 10 minutes.\n\nRegards,\nOutreach Team";
                
                SmtpService::send(
                    $smtpHost,
                    $smtpPort,
                    $smtpUser,
                    $smtpPass,
                    $smtpFromEmail,
                    $smtpFromName,
                    $email,
                    $subject,
                    $body
                );
                $sentSuccessfully = true;
            } catch (Exception $e) {
                $errorMsg = $e->getMessage();
            }
        }

        // Log OTP generation activity
        $db->logActivity((int)$user['id'], 'REQUEST_OTP', "Requested email OTP to {$email}");

        if ($sentSuccessfully) {
            echo json_encode([
                'success' => true,
                'message' => 'OTP sent successfully to your email.'
            ]);
        } else {
            // Return dev_otp if mock or if real send failed
            echo json_encode([
                'success' => true,
                'message' => 'OTP generated (Development mode).',
                'dev_otp' => $otp,
                'error_detail' => $errorMsg
            ]);
        }
        exit;
    }

    if ($action === 'verify') {
        $otp = trim($input['otp'] ?? '');
        if (empty($otp)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'OTP is required.']);
            exit;
        }

        $user = $db->getUserByEmail($email);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found.']);
            exit;
        }

        $isValid = $db->verifyOtp($email, $otp);
        if ($isValid) {
            // Log in user
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            $db->logActivity((int)$user['id'], 'LOGIN_OTP', "Logged in successfully via Email OTP");

            echo json_encode([
                'success' => true,
                'message' => 'OTP verified successfully.',
                'user' => [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid or expired OTP.']);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
