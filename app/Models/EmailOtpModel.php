<?php
namespace App\Models;

use CodeIgniter\Model;

class EmailOtpModel extends Model
{
    protected $table = 'email_otps';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'email',
        'otp',
        'expires_at',
        'used'
    ];
    protected $useTimestamps = false;
}
