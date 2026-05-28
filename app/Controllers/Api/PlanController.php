<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use Exception;

class PlanController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if (!$user || $user['role'] !== 'admin') {
            return $this->respondError('Forbidden. Admin access required.', 403);
        }

        $id = $this->request->getGet('id');
        if ($id) {
            $plan = $this->db->getPlan((int)$id);
            if (!$plan) {
                return $this->respondError('Plan not found.', 404);
            }
            return $this->respondSuccess(['plan' => $plan]);
        }

        $plans = $this->db->getPlans();
        return $this->respondSuccess(['plans' => $plans]);
    }

    public function create(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if (!$user || $user['role'] !== 'admin') {
            return $this->respondError('Forbidden. Admin access required.', 403);
        }

        $input = $this->getJsonInput();
        $id = $input['id'] ?? null;
        $name = trim($input['name'] ?? '');
        $campaignLimit = isset($input['campaign_limit']) ? (int)$input['campaign_limit'] : 10;
        $leadLimit = isset($input['lead_limit']) ? (int)$input['lead_limit'] : 50;
        $llmLimit = isset($input['llm_limit']) ? (int)$input['llm_limit'] : 100;
        $emailLimit = isset($input['email_limit']) ? (int)$input['email_limit'] : 100;
        $whatsappLimit = isset($input['whatsapp_limit']) ? (int)$input['whatsapp_limit'] : 100;
        $smsLimit = isset($input['sms_limit']) ? (int)$input['sms_limit'] : 100;

        if (empty($name)) {
            return $this->respondError('Plan name is required.');
        }

        if ($id) {
            $this->db->updatePlan((int)$id, $name, $campaignLimit, $leadLimit, $llmLimit, $emailLimit, $whatsappLimit, $smsLimit);
            $this->db->logActivity((int)$user['id'], 'UPDATE_PLAN', "Admin updated plan '{$name}' (ID: {$id})");
            return $this->respondSuccess([], 'Plan updated successfully.');
        } else {
            $planId = $this->db->createPlan($name, $campaignLimit, $leadLimit, $llmLimit, $emailLimit, $whatsappLimit, $smsLimit);
            $this->db->logActivity((int)$user['id'], 'CREATE_PLAN', "Admin created plan '{$name}' (ID: {$planId})");
            return $this->respondSuccess(['plan_id' => $planId], 'Plan created successfully.');
        }
    }

    public function delete(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if (!$user || $user['role'] !== 'admin') {
            return $this->respondError('Forbidden. Admin access required.', 403);
        }

        $id = $this->request->getGet('id');
        if (!$id) {
            return $this->respondError('Missing plan ID.');
        }

        $plan = $this->db->getPlan((int)$id);
        if (!$plan) {
            return $this->respondError('Plan not found.', 404);
        }

        if ($plan['name'] === 'Default Plan') {
            return $this->respondError('You cannot delete the Default Plan.');
        }

        $this->db->deletePlan((int)$id);
        $this->db->logActivity((int)$user['id'], 'DELETE_PLAN', "Admin deleted plan '{$plan['name']}' (ID: {$id})");
        return $this->respondSuccess([], 'Plan deleted successfully.');
    }
}
