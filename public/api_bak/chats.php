<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\AuthService;

try {
    $db = new DatabaseService();
    $pdo = $db->getPdo();
    $currentUser = AuthService::getCurrentUser();

    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
        exit;
    }

    // Check if public chat is enabled
    $settings = $db->getSettings();
    $chatEnabled = ($settings['enable_public_chat'] ?? '1') === '1';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$chatEnabled) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Public chat is currently disabled by administrator.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            throw new Exception("Invalid JSON request body.");
        }

        $message = trim($input['message'] ?? '');
        if (empty($message)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Message content cannot be empty.']);
            exit;
        }

        // Insert chat message
        $stmt = $pdo->prepare("INSERT INTO public_chats (user_id, message) VALUES (?, ?)");
        $stmt->execute([$currentUser['id'], $message]);
        $chatId = (int)$pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Chat message sent successfully.',
            'chat_id' => $chatId
        ]);
        exit;
    } else {
        // GET request - return latest 50 messages
        // Even if disabled, we return the status of enable_public_chat so the UI knows if it needs to lock.
        if (!$chatEnabled) {
            echo json_encode([
                'success' => true,
                'chat_enabled' => false,
                'messages' => []
            ]);
            exit;
        }

        $stmt = $pdo->query("
            SELECT c.*, u.username, u.role
            FROM public_chats c
            JOIN users u ON c.user_id = u.id
            ORDER BY c.id DESC
            LIMIT 50
        ");
        $messages = $stmt->fetchAll();

        // Reverse to return chronological order (oldest first)
        $messages = array_reverse($messages);

        echo json_encode([
            'success' => true,
            'chat_enabled' => true,
            'messages' => $messages
        ]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
