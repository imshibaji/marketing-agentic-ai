<?php
namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'username',
        'password_hash',
        'role',
        'full_name',
        'email',
        'mobile',
        'whatsapp_number',
        'plan_campaigns',
        'plan_leads',
        'plan_id',
        'llm_usage',
        'email_usage',
        'whatsapp_usage',
        'sms_usage',
        'plan_expires_at'
    ];
    protected $useTimestamps = false;
}
