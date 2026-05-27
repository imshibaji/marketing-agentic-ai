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
use MarketingAgent\Plugin\HookManager;

try {
    $db = new DatabaseService(); // This triggers plugins autoloading

    $plugin = $_GET['plugin'] ?? '';
    $action = $_GET['action'] ?? '';

    if (empty($plugin) || empty($action)) {
        throw new Exception("Missing plugin or action parameter.");
    }

    // Sanitize plugin ID and action name
    $safePlugin = preg_replace('/[^a-zA-Z0-9_-]/', '', $plugin);
    $safeAction = preg_replace('/[^a-zA-Z0-9_-]/', '', $action);
    $hookName = "api_route_{$safePlugin}_{$safeAction}";

    if (!HookManager::hasAction($hookName)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => "Action '{$action}' for plugin '{$plugin}' is not registered or plugin is deactivated."
        ]);
        exit;
    }

    // Parse input parameters (both JSON post bodies and query params)
    $inputData = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawBody = file_get_contents('php://input');
        $jsonBody = json_decode($rawBody, true);
        $inputData = is_array($jsonBody) ? array_merge($_GET, $jsonBody) : array_merge($_GET, $_POST);
    } else {
        $inputData = $_GET;
    }

    // Execute plugin handler
    HookManager::doAction($hookName, $inputData);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
