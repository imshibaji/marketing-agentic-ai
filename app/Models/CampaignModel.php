<?php
namespace App\Models;

use CodeIgniter\Model;

class CampaignModel extends Model
{
    protected $table = 'campaigns';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'title',
        'product_description',
        'target_audience',
        'channel',
        'status',
        'final_content',
        'crawl_type',
        'crawl_target',
        'language',
        'user_id',
        'llm_provider'
    ];
    protected $useTimestamps = false;
}
