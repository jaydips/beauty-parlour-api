<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Appointments. Times are local (Asia/Kolkata) "Y-m-d H:i:s"; amounts are whole rupees.
 * Cancellation is a status change, never a delete.
 */
class AppointmentModel extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW   = 'no_show';

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_CONFIRMED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_NO_SHOW,
    ];

    /** Statuses that still occupy the calendar slot. */
    public const BLOCKING_STATUSES = [self::STATUS_SCHEDULED, self::STATUS_CONFIRMED, self::STATUS_COMPLETED];

    public const SOURCES = ['walk_in', 'phone', 'whatsapp', 'app'];

    protected $table         = 'appointments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'customer_id', 'starts_at', 'ends_at', 'status', 'source', 'notes',
        'subtotal', 'discount', 'total', 'cancelled_at', 'cancel_reason',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';

    protected $validationRules = [
        'customer_id'   => 'required|is_natural_no_zero',
        'starts_at'     => 'required|valid_date[Y-m-d H:i:s]',
        'ends_at'       => 'required|valid_date[Y-m-d H:i:s]',
        'status'        => 'permit_empty|in_list[scheduled,confirmed,completed,cancelled,no_show]',
        'source'        => 'permit_empty|in_list[walk_in,phone,whatsapp,app]',
        'notes'         => 'permit_empty|string',
        'subtotal'      => 'permit_empty|is_natural',
        'discount'      => 'permit_empty|is_natural',
        'total'         => 'permit_empty|is_natural',
        'cancelled_at'  => 'permit_empty|valid_date[Y-m-d H:i:s]',
        'cancel_reason' => 'permit_empty|string|max_length[255]',
    ];

    /**
     * Appointments that overlap the given window and still block the slot.
     * Used for double-booking checks before saving.
     */
    public function findOverlapping(string $startsAt, string $endsAt, ?int $excludeId = null): array
    {
        $builder = $this->where('starts_at <', $endsAt)
            ->where('ends_at >', $startsAt)
            ->whereIn('status', self::BLOCKING_STATUSES);

        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->orderBy('starts_at', 'ASC')->findAll();
    }

    /**
     * Appointments whose start falls on one local calendar day.
     */
    public function forDay(string $date): array
    {
        return $this->where('starts_at >=', $date . ' 00:00:00')
            ->where('starts_at <=', $date . ' 23:59:59')
            ->orderBy('starts_at', 'ASC')
            ->findAll();
    }
}
