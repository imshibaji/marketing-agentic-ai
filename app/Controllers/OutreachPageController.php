<?php

namespace App\Controllers;

class OutreachPageController extends BaseController
{
    public function index()
    {
        if (!file_exists(ROOTPATH . 'database/install.lock')) {
            return redirect()->to('/install');
        }
        return view('outreach', ['activeTab' => 'outreach']);
    }
}
