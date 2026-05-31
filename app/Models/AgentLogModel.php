<?php
namespace App\Models;

use CodeIgniter\Model;

class AgentLogModel extends Model
{
    protected $table = 'agent_logs';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'campaign_id',
        'agent_name',
        'action',
        'log_text'
    ];
    protected $useTimestamps = false;
}
