<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use Exception;

class UserController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if (!$user || $user['role'] !== 'admin') {
            return $this->respondError('Forbidden. Admin access required.', 403);
        }

        $id = $this->request->getGet('id');
        if ($id) {
            $u = $this->db->getUserById((int)$id);
            if (!$u) {
                return $this->respondError('User not found.', 404);
            }
            return $this->respondSuccess(['user' => $u]);
        }

        $users = $this->db->getUsers();
        return $this->respondSuccess(['users' => $users]);
    }

    public function create(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if (!$user || $user['role'] !== 'admin') {
            return $this->respondError('Forbidden. Admin access required.', 403);
        }

        $input = $this->getJsonInput();
        $id = $input['id'] ?? null;
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');
        $fullName = trim($input['full_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $mobile = trim($input['mobile'] ?? '');
        $whatsapp = trim($input['whatsapp_number'] ?? '');
        $role = trim($input['role'] ?? 'user');
        $planId = isset($input['plan_id']) && $input['plan_id'] !== '' ? (int)$input['plan_id'] : null;

        if (empty($username)) {
            return $this->respondError('Username is required.');
        }

        if ($id) {
            // Update
            $existing = $this->db->getUserById((int)$id);
            if (!$existing) {
                return $this->respondError('User not found.', 404);
            }
            
            $passwordHash = $existing['password_hash'];
            if (!empty($password)) {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            }

            $this->db->updateUser((int)$id, $username, $passwordHash, $role, $fullName, $email, $mobile, $whatsapp, $planId);
            $this->db->logActivity((int)$user['id'], 'UPDATE_USER', "Admin updated user '{$username}' (ID: {$id})");
            return $this->respondSuccess([], 'User updated successfully.');
        } else {
            // Create
            if (empty($password)) {
                return $this->respondError('Password is required for new users.');
            }

            // Check duplicate
            $dup = $this->db->getUserByUsername($username);
            if ($dup) {
                return $this->respondError('Username already exists.');
            }

            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $newId = $this->db->createUser($username, $passwordHash, $role, $fullName, $email, $mobile, $whatsapp, $planId);
            $this->db->logActivity((int)$user['id'], 'CREATE_USER', "Admin created user '{$username}' (ID: {$newId})");

            return $this->respondSuccess(['user_id' => $newId], 'User created successfully.');
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
            return $this->respondError('Missing user ID.');
        }

        if ((int)$id === (int)$user['id']) {
            return $this->respondError('You cannot delete your own account.');
        }

        $existing = $this->db->getUserById((int)$id);
        if (!$existing) {
            return $this->respondError('User not found.', 404);
        }

        $this->db->deleteUser((int)$id);
        $this->db->logActivity((int)$user['id'], 'DELETE_USER', "Admin deleted user '{$existing['username']}' (ID: {$id})");
        return $this->respondSuccess([], 'User deleted successfully.');
    }

    public function activity(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        $logs = $this->db->getActivityLogs((int)$user['id'], $user['role']);
        return $this->respondSuccess(['logs' => $logs]);
    }

    public function stats(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        $pdo = $this->db->getPdo();

        if ($user['role'] === 'admin') {
            $usersCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $campaignsCount = (int)$pdo->query("SELECT COUNT(*) FROM campaigns")->fetchColumn();
            $leadsCount = (int)$pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
        } else {
            $usersCount = 1;
            $stmtC = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE user_id = ?");
            $stmtC->execute([$user['id']]);
            $campaignsCount = (int)$stmtC->fetchColumn();

            $stmtL = $pdo->prepare("
                SELECT COUNT(*) FROM leads l 
                LEFT JOIN campaigns c ON l.campaign_id = c.id 
                WHERE l.user_id = ? OR c.user_id = ?
            ");
            $stmtL->execute([$user['id'], $user['id']]);
            $leadsCount = (int)$stmtL->fetchColumn();
        }

        return $this->respondSuccess([
            'stats' => [
                'users' => $usersCount,
                'campaigns' => $campaignsCount,
                'leads' => $leadsCount
            ]
        ]);
    }

    public function usage(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        $pdo = $this->db->getPdo();
        $userId = (int)$user['id'];

        $stmt = $pdo->prepare("
            SELECT u.*, p.name AS plan_name,
                   p.campaign_limit, p.lead_limit,
                   p.llm_limit,     p.email_limit, p.whatsapp_limit, p.sms_limit
            FROM users u
            LEFT JOIN plans p ON u.plan_id = p.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        $llmUsed = (int)($row['llm_usage'] ?? 0);
        $emailUsed = (int)($row['email_usage'] ?? 0);
        $waUsed = (int)($row['whatsapp_usage'] ?? 0);
        $smsUsed = (int)($row['sms_usage'] ?? 0);
        $llmLimit = (int)($row['llm_limit'] ?? 100);
        $emailLimit = (int)($row['email_limit'] ?? 100);
        $waLimit = (int)($row['whatsapp_limit'] ?? 100);
        $smsLimit = (int)($row['sms_limit'] ?? 100);

        $campaignUsed = $this->db->getUserCampaignCount($userId);
        $leadUsed = $this->db->getUserLeadCount($userId);
        $campaignLimit = (int)$row['campaign_limit'];
        $leadLimit = (int)$row['lead_limit'];

        $settings = $this->db->getSettings();
        $activeProviders = [];

        $geminiActive = ($settings['gemini_active'] ?? '1') === '1';
        if ($geminiActive && !empty($settings['gemini_api_key']) && $settings['gemini_api_key'] !== 'mock') {
            $model = $settings['gemini_model'] ?? 'gemini-1.5-flash';
            $activeProviders[] = [
                'id'    => 'gemini',
                'label' => 'Gemini ' . $model,
                'model' => $model,
                'icon'  => 'fa-google',
                'badge' => 'Cloud',
                'quota' => $llmLimit,
                'used'  => $llmUsed,
            ];
        }

        $lmStudioActive = ($settings['lm_studio_active'] ?? '1') === '1';
        if ($lmStudioActive && !empty($settings['lm_studio_url'])) {
            $model = $settings['lm_studio_model'] ?? 'local';
            $activeProviders[] = [
                'id'    => 'lm_studio',
                'label' => 'LM Studio — ' . $model,
                'model' => $model,
                'icon'  => 'fa-server',
                'badge' => 'Local',
                'quota' => null,
                'used'  => null,
            ];
            if (!empty($settings['lm_studio_extra_model'])) {
                $extraModel = $settings['lm_studio_extra_model'];
                $activeProviders[] = [
                    'id'    => 'lm_studio_extra',
                    'label' => 'LM Studio — ' . $extraModel,
                    'model' => $extraModel,
                    'icon'  => 'fa-server',
                    'badge' => 'Local',
                    'quota' => null,
                    'used'  => null,
                ];
            }
        }

        $ollamaActive = ($settings['ollama_active'] ?? '1') === '1';
        if ($ollamaActive && !empty($settings['ollama_url'])) {
            $model = $settings['ollama_model'] ?? 'llama3';
            $activeProviders[] = [
                'id'    => 'ollama',
                'label' => 'Ollama — ' . $model,
                'model' => $model,
                'icon'  => 'fa-circle-nodes',
                'badge' => 'Local',
                'quota' => null,
                'used'  => null,
            ];
            if (!empty($settings['ollama_extra_model'])) {
                $extraModel = $settings['ollama_extra_model'];
                $activeProviders[] = [
                    'id'    => 'ollama_extra',
                    'label' => 'Ollama — ' . $extraModel,
                    'model' => $extraModel,
                    'icon'  => 'fa-circle-nodes',
                    'badge' => 'Local',
                    'quota' => null,
                    'used'  => null,
                ];
            }
        }

        $openRouterActive = ($settings['openrouter_active'] ?? '0') === '1';
        if ($openRouterActive && !empty($settings['openrouter_api_key'])) {
            $model = $settings['openrouter_model'] ?? 'openai/gpt-4o-mini';
            $activeProviders[] = [
                'id'    => 'openrouter',
                'label' => 'OpenRouter — ' . $model,
                'model' => $model,
                'icon'  => 'fa-route',
                'badge' => 'Cloud',
                'quota' => $llmLimit,
                'used'  => $llmUsed,
            ];
        }

        $openAiCompatActive = ($settings['openai_compat_active'] ?? '0') === '1';
        if ($openAiCompatActive && !empty($settings['openai_compat_url'])) {
            $model = $settings['openai_compat_model'] ?? 'gpt-4o-mini';
            $activeProviders[] = [
                'id'    => 'openai_compatible',
                'label' => 'OpenAI-Compat — ' . $model,
                'model' => $model,
                'icon'  => 'fa-robot',
                'badge' => 'API',
                'quota' => null,
                'used'  => null,
            ];
        }


        return $this->respondSuccess([
            'plan_name' => $row['plan_name'] ?? 'Default Plan',
            'plan_expires_at' => $row['plan_expires_at'] ?? null,
            'active_providers' => $activeProviders,
            'usage' => [
                'llm'       => ['used' => $llmUsed,      'limit' => $llmLimit],
                'email'     => ['used' => $emailUsed,     'limit' => $emailLimit],
                'whatsapp'  => ['used' => $waUsed,        'limit' => $waLimit],
                'sms'       => ['used' => $smsUsed,       'limit' => $smsLimit],
                'campaigns' => ['used' => $campaignUsed,  'limit' => $campaignLimit],
                'leads'     => ['used' => $leadUsed,      'limit' => $leadLimit],
            ],
        ]);
    }

    public function profile(): ResponseInterface
    {
        try {
            $user = $this->getCurrentUser();
            if (!$user) {
                return $this->respondError('Unauthorized. Please login.', 401);
            }

            $method = $this->request->getMethod();

            if (strtolower($method) === 'get') {
                $profile = $this->db->getUserById((int)$user['id']);
                if (!$profile) {
                    return $this->respondError('User not found.', 404);
                }
                unset($profile['password_hash']);
                return $this->respondSuccess(['profile' => $profile]);
            }

            if (strtolower($method) === 'put' || strtolower($method) === 'post') {
                $input = $this->getJsonInput();
                $existing = $this->db->getUserById((int)$user['id']);
                if (!$existing) {
                    return $this->respondError('User not found.', 404);
                }

                $username = isset($input['username']) ? trim($input['username']) : $existing['username'];
                $password = $input['password'] ?? null;
                $fullName = isset($input['full_name']) ? trim($input['full_name']) : $existing['full_name'];
                $email    = isset($input['email']) ? trim($input['email']) : $existing['email'];
                $mobile   = isset($input['mobile']) ? trim($input['mobile']) : $existing['mobile'];
                $whatsapp = isset($input['whatsapp_number']) ? trim($input['whatsapp_number']) : $existing['whatsapp_number'];

                if (strlen($username) < 3) {
                    return $this->respondError('Username must be at least 3 characters.');
                }
                if ($password !== null && strlen($password) > 0 && strlen($password) < 6) {
                    return $this->respondError('Password must be at least 6 characters.');
                }

                if ($username !== $existing['username']) {
                    $dup = $this->db->getUserByUsername($username);
                    if ($dup) {
                        return $this->respondError('Username already taken.', 409);
                    }
                }

                $newHash = ($password && strlen($password) >= 6)
                            ? password_hash($password, PASSWORD_BCRYPT)
                            : $existing['password_hash'];

                $this->db->updateUser(
                    (int)$user['id'],
                    $username,
                    $newHash,
                    $existing['role'],
                    $fullName,
                    $email,
                    $mobile,
                    $whatsapp,
                    (int)$existing['plan_id']
                );

                // Update session
                $session = session();
                $sUser = $session->get('user');
                $sUser['username'] = $username;
                $session->set('user', $sUser);

                $this->db->logActivity((int)$user['id'], 'UPDATE_PROFILE', "Updated own profile settings");

                return $this->respondSuccess([], 'Profile updated successfully.');
            }

            return $this->respondError('Method not allowed.', 405);
        } catch (Exception $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }

    public function resetUsage(): ResponseInterface
    {
        $admin = $this->getCurrentUser();
        if (!$admin || $admin['role'] !== 'admin') {
            return $this->respondError('Forbidden. Admin access required.', 403);
        }

        $input = $this->getJsonInput();
        $userId = $input['user_id'] ?? null;
        if (!$userId) {
            $userId = $this->request->getGet('user_id');
        }

        if (!$userId) {
            return $this->respondError('Missing user ID.');
        }

        $u = $this->db->getUserById((int)$userId);
        if (!$u) {
            return $this->respondError('User not found.', 404);
        }

        $this->db->resetUserUsage((int)$userId);
        $this->db->logActivity((int)$admin['id'], 'RESET_USAGE', "Admin reset usage for user '{$u['username']}' (ID: {$userId})");

        return $this->respondSuccess([], 'User usage reset successfully.');
    }

    public function resetPlan(): ResponseInterface
    {
        $admin = $this->getCurrentUser();
        if (!$admin || $admin['role'] !== 'admin') {
            return $this->respondError('Forbidden. Admin access required.', 403);
        }

        $input = $this->getJsonInput();
        $userId = $input['user_id'] ?? null;
        if (!$userId) {
            $userId = $this->request->getGet('user_id');
        }

        if (!$userId) {
            return $this->respondError('Missing user ID.');
        }

        $u = $this->db->getUserById((int)$userId);
        if (!$u) {
            return $this->respondError('User not found.', 404);
        }

        $this->db->resetUserPlan((int)$userId);
        $this->db->logActivity((int)$admin['id'], 'RESET_PLAN_EXPIRY', "Admin reset/renewed plan expiration for user '{$u['username']}' (ID: {$userId})");

        return $this->respondSuccess([], 'User plan reset/renewed successfully.');
    }
}
