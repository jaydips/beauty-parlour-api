<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Money received (type "payment") or returned (type "refund"); amounts are whole
 * rupees and always positive. An appointment may have several payments
 * (advance + balance) and a payment may exist without an appointment.
 */
class PaymentModel extends Model
{
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_REFUND  = 'refund';
    public const TYPES        = [self::TYPE_PAYMENT, self::TYPE_REFUND];
    public const METHODS      = ['cash', 'upi', 'card', 'other'];

    protected $table         = 'payments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['customer_id', 'appointment_id', 'type', 'method', 'amount', 'paid_at', 'reference', 'notes'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';

    protected $validationRules = [
        'customer_id'    => 'required|is_natural_no_zero',
        'appointment_id' => 'permit_empty|is_natural_no_zero',
        'type'           => 'permit_empty|in_list[payment,refund]',
        'method'         => 'required|in_list[cash,upi,card,other]',
        'amount'         => 'required|is_natural_no_zero',
        'paid_at'        => 'required|valid_date[Y-m-d H:i:s]',
        'reference'      => 'permit_empty|string|max_length[100]',
        'notes'          => 'permit_empty|string',
    ];

    /**
     * Net amount paid for an appointment: payments minus refunds, in rupees.
     */
    public function netPaidForAppointment(int $appointmentId): int
    {
        $rows = $this->select('type, SUM(amount) AS total')
            ->where('appointment_id', $appointmentId)
            ->groupBy('type')
            ->findAll();

        $net = 0;

        foreach ($rows as $row) {
            $net += $row['type'] === self::TYPE_REFUND ? -(int) $row['total'] : (int) $row['total'];
        }

        return $net;
    }
}
