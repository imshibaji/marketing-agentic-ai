<?php
namespace App\Models;

use CodeIgniter\Model;

class UserActivityLogModel extends Model
{
    protected $table = 'user_activity_logs';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'user_id',
        'action',
        'details'
    ];
    protected $useTimestamps = false;
}
