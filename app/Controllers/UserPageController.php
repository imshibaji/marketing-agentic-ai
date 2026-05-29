<?php

namespace App\Controllers;

class UserPageController extends BaseController
{
    public function index()
    {
        if (!file_exists(ROOTPATH . 'database/install.lock')) {
            return redirect()->to('/install');
        }
        
        $session = session();
        $user = $session->get('user');
        if (!$user || $user['role'] !== 'admin') {
            return redirect()->to('/dashboard');
        }

        return view('users', ['activeTab' => 'users']);
    }
}
