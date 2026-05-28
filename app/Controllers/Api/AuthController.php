<?php

namespace App\Controllers\Api;

use MarketingAgent\Service\AuthService;
use CodeIgniter\HTTP\ResponseInterface;

class AuthController extends BaseApiController
{
    public function login(): ResponseInterface
    {
        $input = $this->getJsonInput();
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        if (empty($username) || empty($password)) {
            return $this->respondError('Username and password are required.');
        }

        $auth = new AuthService($this->db);
        $res = $auth->login($username, $password);

        if ($res['success']) {
            $this->db->logActivity((int)$res['user']['id'], 'LOGIN', "Logged in successfully via password");
            return $this->respondSuccess(['user' => $res['user']], $res['message']);
        }

        return $this->respondError($res['error']);
    }

    public function logout(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if ($user) {
            $this->db->logActivity((int)$user['id'], 'LOGOUT', "Logged out successfully");
        }
        AuthService::logout();
        return $this->respondSuccess([], 'Logged out successfully.');
    }

    public function check(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if ($user) {
            return $this->respondSuccess(['user' => $user]);
        }
        return $this->respondError('Not logged in', 401);
    }

    public function register(): ResponseInterface
    {
        $input = $this->getJsonInput();
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        $fullName = $input['full_name'] ?? null;
        $email = $input['email'] ?? null;
        $mobile = $input['mobile'] ?? null;
        $whatsapp = $input['whatsapp_number'] ?? null;
        $role = $input['role'] ?? 'user';

        if (empty($username) || empty($password)) {
            return $this->respondError('Username and password are required.');
        }

        $auth = new AuthService($this->db);
        $res = $auth->register($username, $password, $role, $fullName, $email, $mobile, $whatsapp);

        if ($res['success']) {
            return $this->respondSuccess([], $res['message']);
        }
        return $this->respondError($res['error']);
    }

    public function sendOtp(): ResponseInterface
    {
        $input = $this->getJsonInput();
        $email = $input['email'] ?? '';
        if (empty($email)) {
            return $this->respondError('Email is required.');
        }

        // Generate OTP
        $otp = sprintf("%06d", mt_rand(100000, 999999));
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes

        $this->db->createOtp($email, $otp, $expiresAt);

        // For development we return it
        return $this->respondSuccess(['otp' => $otp], 'OTP sent successfully.');
    }

    public function verifyOtp(): ResponseInterface
    {
        $input = $this->getJsonInput();
        $email = $input['email'] ?? '';
        $otp = $input['otp'] ?? '';

        if (empty($email) || empty($otp)) {
            return $this->respondError('Email and OTP are required.');
        }

        $verified = $this->db->verifyOtp($email, $otp);
        if ($verified) {
            // Find user
            $user = $this->db->getUserByEmail($email);
            if ($user) {
                $session = session();
                $userData = [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ];
                $session->set('user', $userData);
                $this->db->logActivity((int)$user['id'], 'LOGIN_OTP', "Logged in via OTP");
                return $this->respondSuccess(['user' => $userData], 'OTP verified, logged in.');
            }
            return $this->respondError('No user registered with this email.');
        }

        return $this->respondError('Invalid or expired OTP.');
    }

    public function otp(): ResponseInterface
    {
        $input = $this->getJsonInput();
        $action = $input['action'] ?? '';
        if ($action === 'send_otp') {
            return $this->sendOtp();
        } elseif ($action === 'verify_otp') {
            return $this->verifyOtp();
        }
        return $this->respondError('Invalid OTP action.');
    }
}
