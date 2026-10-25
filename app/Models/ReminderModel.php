<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

/**
 * The owner's manual WhatsApp to-do list. The server never sends messages:
 * the app shows due reminders with a click-to-chat link and the owner marks
 * them as sent after sending from their own WhatsApp.
 */
class ReminderModel extends Model
{
    public const TYPES    = ['appointment', 'follow_up', 'birthday', 'review', 'custom'];
    public const STATUSES = ['pending', 'sent', 'skipped', 'cancelled'];

    protected $table         = 'reminders';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['customer_id', 'appointment_id', 'type', 'due_at', 'channel', 'message', 'status', 'sent_at'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';

    protected $validationRules = [
        'customer_id'    => 'required|is_natural_no_zero',
        'appointment_id' => 'permit_empty|is_natural_no_zero',
        'type'           => 'required|in_list[appointment,follow_up,birthday,review,custom]',
        'due_at'         => 'required|valid_date[Y-m-d H:i:s]',
        'channel'        => 'permit_empty|in_list[whatsapp]',
        'message'        => 'required|string',
        'status'         => 'permit_empty|in_list[pending,sent,skipped,cancelled]',
        'sent_at'        => 'permit_empty|valid_date[Y-m-d H:i:s]',
    ];

    /**
     * Pending reminders that are due at or before $now (local time), oldest first.
     */
    public function due(?string $now = null): array
    {
        $now ??= Time::now()->toDateTimeString();

        return $this->where('status', 'pending')
            ->where('due_at <=', $now)
            ->orderBy('due_at', 'ASC')
            ->findAll();
    }

    /**
     * Marks a reminder as sent by the owner.
     */
    public function markSent(int $id): bool
    {
        return $this->update($id, ['status' => 'sent', 'sent_at' => Time::now()->toDateTimeString()]);
    }
}
