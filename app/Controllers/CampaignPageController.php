<?php

namespace App\Controllers;

class CampaignPageController extends BaseController
{
    public function index()
    {
        if (!file_exists(ROOTPATH . 'database/install.lock')) {
            return redirect()->to('/install');
        }
        return view('campaigns', ['activeTab' => 'campaigns']);
    }
}
