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
use MarketingAgent\Plugin\PluginManager;

try {
    // Authenticate user & check admin permissions
    $user = AuthService::getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden. Only administrators can manage plugins.']);
        exit;
    }

    $db = new DatabaseService();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $pluginId = $input['plugin'] ?? '';

        if (empty($action) || empty($pluginId)) {
            throw new Exception("Missing action or plugin parameter.");
        }

        if ($action === 'activate') {
            $result = PluginManager::activatePlugin($pluginId);
            if ($result) {
                echo json_encode(['success' => true, 'message' => "Plugin '{$pluginId}' activated successfully."]);
            } else {
                throw new Exception("Failed to activate plugin '{$pluginId}'.");
            }
        } elseif ($action === 'deactivate') {
            $result = PluginManager::deactivatePlugin($pluginId);
            if ($result) {
                echo json_encode(['success' => true, 'message' => "Plugin '{$pluginId}' deactivated successfully."]);
            } else {
                throw new Exception("Failed to deactivate plugin '{$pluginId}'.");
            }
        } else {
            throw new Exception("Invalid action. Must be 'activate' or 'deactivate'.");
        }
    } else {
        // GET request — list installed plugins
        $plugins = PluginManager::getInstalledPlugins();
        echo json_encode(['success' => true, 'plugins' => $plugins]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
