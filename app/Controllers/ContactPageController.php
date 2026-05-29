<?php

namespace App\Controllers;

class ContactPageController extends BaseController
{
    public function index()
    {
        if (!file_exists(ROOTPATH . 'database/install.lock')) {
            return redirect()->to('/install');
        }
        return view('contacts', ['activeTab' => 'contacts']);
    }
}
