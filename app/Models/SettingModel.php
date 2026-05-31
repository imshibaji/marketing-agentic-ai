<?php
namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table = 'settings';
    protected $primaryKey = 'key';
    protected $useAutoIncrement = false;
    protected $allowedFields = [
        'key',
        'value'
    ];
    protected $useTimestamps = false;
}
