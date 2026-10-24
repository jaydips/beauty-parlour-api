<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

/**
 * WhatsApp outbox: every automatic message (booking confirmation, reminder, follow-up, birthday,
 * offer) is a row here. `spark whatsapp:send` (cron, every minute) delivers queued rows through the
 * bridge and records the result, so the owner can see what was sent, what failed and why.
 */
class MessageModel extends Model
{
    public const TYPES    = ['booking', 'appointment', 'follow_up', 'birthday', 'offer', 'custom'];
    public const STATUSES = ['queued', 'sent', 'failed', 'cancelled'];

    protected $table         = 'messages';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['customer_id', 'appointment_id', 'reminder_id', 'campaign_id', 'phone', 'type', 'body', 'status', 'attempts', 'last_error', 'scheduled_at', 'sent_at', 'wa_id'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';

    protected $validationRules = [
        'customer_id'    => 'permit_empty|is_natural_no_zero',
        'appointment_id' => 'permit_empty|is_natural_no_zero',
        'reminder_id'    => 'permit_empty|is_natural_no_zero',
        'campaign_id'    => 'permit_empty|is_natural_no_zero',
        'phone'          => 'required|regex_match[/^[0-9]{10}$/]',
        'type'           => 'required|in_list[booking,appointment,follow_up,birthday,offer,custom]',
        'body'           => 'required|string',
        'status'         => 'permit_empty|in_list[queued,sent,failed,cancelled]',
        'attempts'       => 'permit_empty|is_natural',
        'last_error'     => 'permit_empty|string|max_length[255]',
        'scheduled_at'   => 'required|valid_date[Y-m-d H:i:s]',
        'sent_at'        => 'permit_empty|valid_date[Y-m-d H:i:s]',
        'wa_id'          => 'permit_empty|string|max_length[64]',
    ];

    /**
     * Queued messages whose time has come, oldest first.
     */
    public function dueBatch(string $now, int $limit): array
    {
        return $this->where('status', 'queued')
            ->where('scheduled_at <=', $now)
            ->orderBy('scheduled_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll(max(1, $limit));
    }

    /**
     * Row counts per status, optionally restricted (e.g. ['campaign_id' => 3]).
     *
     * @return array<string, int>
     */
    public function counts(array $where = []): array
    {
        $builder = $this->builder()->select('status, COUNT(*) AS n')->groupBy('status');

        foreach ($where as $key => $value) {
            $builder->where($key, $value);
        }

        $out = array_fill_keys(self::STATUSES, 0);

        foreach ($builder->get()->getResultArray() as $row) {
            $out[(string) $row['status']] = (int) $row['n'];
        }

        return $out;
    }

    /**
     * Puts a failed or cancelled message back in the queue for an immediate retry.
     */
    public function requeue(int $id): bool
    {
        return $this->update($id, ['status' => 'queued', 'scheduled_at' => Time::now()->toDateTimeString(), 'last_error' => null]);
    }

    public function cancel(int $id): bool
    {
        return $this->update($id, ['status' => 'cancelled']);
    }
}
