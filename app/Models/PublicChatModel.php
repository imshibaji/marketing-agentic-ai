<?php
namespace App\Models;

use CodeIgniter\Model;

class PublicChatModel extends Model
{
    protected $table = 'public_chats';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'user_id',
        'message'
    ];
    protected $useTimestamps = false;
}
