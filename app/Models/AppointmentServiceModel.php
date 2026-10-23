<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Line items of an appointment with a snapshot of the service name, duration and
 * price at booking time, so later price-list changes never rewrite history.
 */
class AppointmentServiceModel extends Model
{
    protected $table         = 'appointment_services';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['appointment_id', 'service_id', 'service_name', 'duration_minutes', 'price', 'quantity'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'appointment_id'   => 'required|is_natural_no_zero',
        'service_id'       => 'permit_empty|is_natural_no_zero',
        'service_name'     => 'required|string|max_length[100]',
        'duration_minutes' => 'required|is_natural_no_zero',
        'price'            => 'required|is_natural',
        'quantity'         => 'permit_empty|is_natural_no_zero',
    ];

    public function forAppointment(int $appointmentId): array
    {
        return $this->where('appointment_id', $appointmentId)->orderBy('id', 'ASC')->findAll();
    }
}
