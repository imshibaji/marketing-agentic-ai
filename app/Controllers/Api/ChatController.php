<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use Exception;
use PDO;

class ChatController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        $settings = $this->db->getSettings();
        $chatEnabled = ($settings['enable_public_chat'] ?? '1') === '1';

        if (!$chatEnabled) {
            return $this->respondSuccess([
                'chat_enabled' => false,
                'messages' => []
            ]);
        }

        $pdo = $this->db->getPdo();
        $stmt = $pdo->query("
            SELECT c.*, u.username, u.role
            FROM public_chats c
            JOIN users u ON c.user_id = u.id
            ORDER BY c.id DESC
            LIMIT 50
        ");
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Reverse to return chronological order (oldest first)
        $messages = array_reverse($messages);

        return $this->respondSuccess([
            'chat_enabled' => true,
            'messages' => $messages
        ]);
    }

    public function create(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        $settings = $this->db->getSettings();
        $chatEnabled = ($settings['enable_public_chat'] ?? '1') === '1';

        if (!$chatEnabled) {
            return $this->respondError('Public chat is currently disabled by administrator.', 403);
        }

        $input = $this->getJsonInput();
        $message = trim($input['message'] ?? '');

        if (empty($message)) {
            return $this->respondError('Message content cannot be empty.');
        }

        $pdo = $this->db->getPdo();
        $stmt = $pdo->prepare("INSERT INTO public_chats (user_id, message) VALUES (?, ?)");
        $stmt->execute([$user['id'], $message]);
        $chatId = (int)$pdo->lastInsertId();

        return $this->respondSuccess(['chat_id' => $chatId], 'Chat message sent successfully.');
    }
}
