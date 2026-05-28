<?php

namespace App\Controllers\Api;

use MarketingAgent\Service\SmtpService;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;

class NotificationController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        try {
            $pdo = $this->db->getPdo();
            $stmt = $pdo->query("
                SELECT n.*, u.username AS sender_username, u.role AS sender_role
                FROM notifications n
                LEFT JOIN users u ON n.sender_id = u.id
                ORDER BY n.id DESC
            ");
            $notifications = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->respondSuccess(['notifications' => $notifications]);
        } catch (Exception $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }

    public function create(): ResponseInterface
    {
        try {
            $currentUser = $this->getCurrentUser();
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                return $this->respondError('Forbidden. Only administrators can publish notifications.', 403);
            }

            $input = $this->getJsonInput();
            $title = trim($input['title'] ?? '');
            $message = trim($input['message'] ?? '');

            if (empty($title) || empty($message)) {
                return $this->respondError('Title and message are required.');
            }

            $pdo = $this->db->getPdo();
            $stmt = $pdo->prepare("INSERT INTO notifications (sender_id, title, message) VALUES (?, ?, ?)");
            $stmt->execute([$currentUser['id'], $title, $message]);
            $notificationId = (int)$pdo->lastInsertId();

            $this->db->logActivity($currentUser['id'], 'PUBLISHED_NOTIFICATION', "Published notification '{$title}'");

            // Parse mentions e.g. @username
            $mentions = [];
            if (preg_match_all('/@([a-zA-Z0-9_-]+)/', $message, $matches)) {
                $mentions = array_unique($matches[1]);
            }

            $emailResults = [];
            if (!empty($mentions)) {
                $settings = $this->db->getSettings();
                $smtpHost = $settings['smtp_host'] ?? 'mock';
                $smtpPort = (int)($settings['smtp_port'] ?? 587);
                $smtpUser = $settings['smtp_user'] ?? '';
                $smtpPass = $settings['smtp_pass'] ?? '';
                $smtpFromEmail = $settings['smtp_from_email'] ?? 'outreach@example.com';
                $smtpFromName = $settings['smtp_from_name'] ?? 'System Notifications';

                $isMock = ($smtpHost === 'mock' || empty($smtpHost));

                foreach ($mentions as $username) {
                    $user = $this->db->getUserByUsername($username);
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
                            $this->db->logActivity(
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
                                $this->db->logActivity(
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
                                $this->db->logActivity(
                                    $currentUser['id'],
                                    'EMAIL_MENTION_FAIL',
                                    "Failed to send email to mentioned user {$username} ({$toEmail}): " . $mailEx->getMessage()
                                );
                            }
                        }
                    }
                }
            }

            return $this->respondSuccess([
                'message' => 'Notification published successfully.',
                'notification_id' => $notificationId,
                'email_mentions' => $emailResults
            ]);
        } catch (Exception $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }

    public function delete($id = null): ResponseInterface
    {
        try {
            $currentUser = $this->getCurrentUser();
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                return $this->respondError('Forbidden. Only administrators can delete notifications.', 403);
            }

            if (!$id) {
                $id = $this->request->getGet('id');
            }
            if (!$id) {
                $input = $this->getJsonInput();
                $id = $input['id'] ?? null;
            }

            if (!$id) {
                return $this->respondError('Missing notification ID.');
            }

            $pdo = $this->db->getPdo();
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
            $stmt->execute([$id]);

            $this->db->logActivity($currentUser['id'], 'DELETED_NOTIFICATION', "Deleted notification ID {$id}");

            return $this->respondSuccess([], 'Notification deleted successfully.');
        } catch (Exception $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }
}
