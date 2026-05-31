<?php
namespace App\Models;

use CodeIgniter\Model;

class LeadModel extends Model
{
    protected $table = 'leads';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'campaign_id',
        'company_name',
        'contact_name',
        'email',
        'whatsapp',
        'mobile',
        'industry',
        'description',
        'score',
        'reasoning',
        'email_draft',
        'whatsapp_draft',
        'sms_draft',
        'status',
        'user_id',
        'source',
        'postal_address',
        'calls_draft'
    ];
    protected $useTimestamps = false;
}
