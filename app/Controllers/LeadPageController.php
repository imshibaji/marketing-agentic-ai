<?php

namespace App\Controllers;

class LeadPageController extends BaseController
{
    public function index()
    {
        if (!file_exists(ROOTPATH . 'database/install.lock')) {
            return redirect()->to('/install');
        }
        return view('leads', ['activeTab' => 'leads']);
    }
}
