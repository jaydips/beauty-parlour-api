<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Offer / announcement broadcasts: one row per campaign, one `messages` row per recipient.
 * audience: all (every opted-in customer), recent (visited within audience_days),
 * inactive (no completed visit within audience_days).
 */
class CampaignModel extends Model
{
    public const AUDIENCES = ['all', 'recent', 'inactive'];
    public const STATUSES  = ['queued', 'done', 'cancelled'];

    protected $table         = 'campaigns';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['title', 'body', 'audience', 'audience_days', 'recipients', 'sent', 'failed', 'status'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';

    protected $validationRules = [
        'title'         => 'required|string|max_length[120]',
        'body'          => 'required|string|max_length[2000]',
        'audience'      => 'required|in_list[all,recent,inactive]',
        'audience_days' => 'permit_empty|is_natural',
        'recipients'    => 'permit_empty|is_natural',
        'sent'          => 'permit_empty|is_natural',
        'failed'        => 'permit_empty|is_natural',
        'status'        => 'permit_empty|in_list[queued,done,cancelled]',
    ];
}
