<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        if (!file_exists(ROOTPATH . 'database/install.lock')) {
            return redirect()->to('/install');
        }
        return view('dashboard');
    }
}
