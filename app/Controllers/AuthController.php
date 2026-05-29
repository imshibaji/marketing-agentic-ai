<?php

namespace App\Controllers;

use MarketingAgent\Service\AuthService;

class AuthController extends BaseController
{
    public function login()
    {
        if (!file_exists(ROOTPATH . 'database/install.lock')) {
            return redirect()->to('/install');
        }
        return view('login');
    }

    public function register()
    {
        if (!file_exists(ROOTPATH . 'database/install.lock')) {
            return redirect()->to('/install');
        }
        return view('register');
    }

    public function logout()
    {
        AuthService::logout();
        return redirect()->to('/login');
    }
}
