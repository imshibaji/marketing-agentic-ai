<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\AuthService;
use MarketingAgent\Service\SmtpService;

try {
    $db = new DatabaseService();
    $pdo = $db->getPdo();
    $currentUser = AuthService::getCurrentUser();

    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        if ($currentUser['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden. Only administrators can delete notifications.']);
            exit;
        }

        $id = $_GET['id'] ?? null;
        if (!$id) {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing notification ID.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$id]);

        $db->logActivity($currentUser['id'], 'DELETED_NOTIFICATION', "Deleted notification ID {$id}");

        echo json_encode(['success' => true, 'message' => 'Notification deleted successfully.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Admin only can publish notifications
        if ($currentUser['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden. Only administrators can publish notifications.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            throw new Exception("Invalid JSON request body.");
        }

        $title = trim($input['title'] ?? '');
        $message = trim($input['message'] ?? '');

        if (empty($title) || empty($message)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Title and message are required.']);
            exit;
        }

        // Insert notification
        $stmt = $pdo->prepare("INSERT INTO notifications (sender_id, title, message) VALUES (?, ?, ?)");
        $stmt->execute([$currentUser['id'], $title, $message]);
        $notificationId = (int)$pdo->lastInsertId();

        // Log notification creation
        $db->logActivity($currentUser['id'], 'PUBLISHED_NOTIFICATION', "Published notification '{$title}'");

        // Parse mentions e.g. @username
        $mentions = [];
        if (preg_match_all('/@([a-zA-Z0-9_-]+)/', $message, $matches)) {
            $mentions = array_unique($matches[1]);
        }

        $emailResults = [];
        if (!empty($mentions)) {
            // Retrieve SMTP settings
            $settings = $db->getSettings();
            $smtpHost = $settings['smtp_host'] ?? 'mock';
            $smtpPort = (int)($settings['smtp_port'] ?? 587);
            $smtpUser = $settings['smtp_user'] ?? '';
            $smtpPass = $settings['smtp_pass'] ?? '';
            $smtpFromEmail = $settings['smtp_from_email'] ?? 'outreach@example.com';
            $smtpFromName = $settings['smtp_from_name'] ?? 'System Notifications';

            $isMock = ($smtpHost === 'mock' || empty($smtpHost));

            foreach ($mentions as $username) {
                $user = $db->getUserByUsername($username);
                if ($user && !empty($user['email'])) {
                    $toEmail = $user['email'];
                    $subject = "Notification Mention: " . $title;
                    $body = "Hello {$user['username']},\n\n"
                          . "You were mentioned in a system notification by Administrator @{$currentUser['username']}:\n\n"
                          . "Title: {$title}\n"
                          . "Message:\n"
                          . "{$message}\n\n"
                          . "Best regards,\n"
                          . "{$smtpFromName}";

                    if ($isMock) {
                        $emailResults[] = [
                            'username' => $username,
                            'email' => $toEmail,
                            'status' => 'mocked',
                            'detail' => 'Mock email logged (Development Mode).'
                        ];
                        $db->logActivity(
                            $currentUser['id'],
                            'EMAIL_MENTION_MOCK',
                            "Mock notification email sent to mentioned user {$username} ({$toEmail})"
                        );
                    } else {
                        try {
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
                            $emailResults[] = [
                                'username' => $username,
                                'email' => $toEmail,
                                'status' => 'sent',
                                'detail' => 'Email sent successfully.'
                            ];
                            $db->logActivity(
                                $currentUser['id'],
                                'EMAIL_MENTION_SENT',
                                "Notification email sent to mentioned user {$username} ({$toEmail})"
                            );
                        } catch (Exception $mailEx) {
                            $emailResults[] = [
                                'username' => $username,
                                'email' => $toEmail,
                                'status' => 'failed',
                                'detail' => $mailEx->getMessage()
                            ];
                            $db->logActivity(
                                $currentUser['id'],
                                'EMAIL_MENTION_FAIL',
                                "Failed to send email to mentioned user {$username} ({$toEmail}): " . $mailEx->getMessage()
                            );
                        }
                    }
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Notification published successfully.',
            'notification_id' => $notificationId,
            'email_mentions' => $emailResults
        ]);
        exit;
    } else {
        // GET Request — list notifications
        $stmt = $pdo->query("
            SELECT n.*, u.username AS sender_username, u.role AS sender_role
            FROM notifications n
            LEFT JOIN users u ON n.sender_id = u.id
            ORDER BY n.id DESC
        ");
        $notifications = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'notifications' => $notifications
        ]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
