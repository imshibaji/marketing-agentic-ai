<?php
namespace App\Models;

use CodeIgniter\Model;

class PlanModel extends Model
{
    protected $table = 'plans';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'name',
        'campaign_limit',
        'lead_limit',
        'llm_limit',
        'email_limit',
        'whatsapp_limit',
        'sms_limit',
        'duration'
    ];
    protected $useTimestamps = false;
}
