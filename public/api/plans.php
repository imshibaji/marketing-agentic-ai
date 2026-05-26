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

try {
    $db = new DatabaseService();
    $user = AuthService::getCurrentUser();

    // Verify Admin role
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden. Admin access required.']);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $plan = $db->getPlan((int)$id);
            if (!$plan) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Plan not found.']);
                exit;
            }
            echo json_encode(['success' => true, 'plan' => $plan]);
        } else {
            $plans = $db->getPlans();
            echo json_encode(['success' => true, 'plans' => $plans]);
        }
        exit;

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            throw new Exception("Invalid JSON request body.");
        }

        $id = $input['id'] ?? null;
        $name = trim($input['name'] ?? '');
        $campaignLimit = isset($input['campaign_limit']) ? (int)$input['campaign_limit'] : 10;
        $leadLimit = isset($input['lead_limit']) ? (int)$input['lead_limit'] : 50;
        $llmLimit = isset($input['llm_limit']) ? (int)$input['llm_limit'] : 100;
        $emailLimit = isset($input['email_limit']) ? (int)$input['email_limit'] : 100;
        $whatsappLimit = isset($input['whatsapp_limit']) ? (int)$input['whatsapp_limit'] : 100;

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Plan name is required.']);
            exit;
        }

        if ($id) {
            // Update plan
            $db->updatePlan((int)$id, $name, $campaignLimit, $leadLimit, $llmLimit, $emailLimit, $whatsappLimit);
            $db->logActivity((int)$user['id'], 'UPDATE_PLAN', "Admin updated plan '{$name}' (ID: {$id})");
            echo json_encode(['success' => true, 'message' => 'Plan updated successfully.']);
        } else {
            // Create plan
            $planId = $db->createPlan($name, $campaignLimit, $leadLimit, $llmLimit, $emailLimit, $whatsappLimit);
            $db->logActivity((int)$user['id'], 'CREATE_PLAN', "Admin created plan '{$name}' (ID: {$planId})");
            echo json_encode(['success' => true, 'message' => 'Plan created successfully.', 'plan_id' => $planId]);
        }
        exit;

    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing plan ID.']);
            exit;
        }

        $plan = $db->getPlan((int)$id);
        if (!$plan) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Plan not found.']);
            exit;
        }

        if ($plan['name'] === 'Default Plan') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'You cannot delete the Default Plan.']);
            exit;
        }

        $db->deletePlan((int)$id);
        $db->logActivity((int)$user['id'], 'DELETE_PLAN', "Admin deleted plan '{$plan['name']}' (ID: {$id})");
        echo json_encode(['success' => true, 'message' => 'Plan deleted successfully.']);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
