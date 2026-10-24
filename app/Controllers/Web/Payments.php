<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\PaymentModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;

/**
 * Payments in the web UI.
 *   POST /app/payments                 add a payment (from an appointment or a customer page)
 *   POST /app/payments/{id}/delete     remove a wrongly entered payment
 *   GET  /app/payments?from=&to=       list with totals (chunk 3)
 */
class Payments extends WebController
{
    public function store(): RedirectResponse
    {
        $data          = $this->input();
        $appointmentId = (int) ($data['appointment_id'] ?? 0);
        $customerId    = (int) ($data['customer_id'] ?? 0);
        $amount        = (int) preg_replace('/\D+/', '', (string) ($data['amount'] ?? ''));
        $method        = (string) ($data['method'] ?? 'cash');
        $type          = ($data['type'] ?? 'payment') === 'refund' ? 'refund' : 'payment';

        $appointment = $appointmentId > 0 ? (new AppointmentModel())->find($appointmentId) : null;

        if ($appointment !== null) {
            $customerId = (int) $appointment['customer_id'];
        } elseif ($appointmentId > 0) {
            return $this->back('app')->with('error', 'Appointment મળી નહીં.');
        }

        if ($customerId <= 0 || (new CustomerModel())->find($customerId) === null) {
            return $this->back('app')->with('error', 'Customer મળ્યો નહીં.');
        }
        if ($amount <= 0) {
            return $this->back('app')->with('error', 'રકમ 1 કે વધુ હોવી જોઈએ.');
        }
        if (! in_array($method, PaymentModel::METHODS, true)) {
            return $this->back('app')->with('error', 'Payment method ખોટી છે.');
        }

        $model = new PaymentModel();
        $id    = $model->insert([
            'customer_id'    => $customerId,
            'appointment_id' => $appointment ? (int) $appointment['id'] : null,
            'type'           => $type,
            'method'         => $method,
            'amount'         => $amount,
            'paid_at'        => Time::now()->toDateTimeString(),
            'reference'      => trim((string) ($data['reference'] ?? '')) ?: null,
            'notes'          => trim((string) ($data['notes'] ?? '')) ?: null,
        ]);

        if ($id === false) {
            return $this->back('app')->with('error', implode(' ', $model->errors()));
        }

        $target = $appointment ? 'app/appointments/' . $appointment['id'] : 'app/customers/' . $customerId;

        return redirect()->to(site_url($target))->with('success', ($type === 'refund' ? 'Refund' : 'Payment') . ' ' . rupees($amount) . ' નોંધાયું.');
    }

    public function delete(int $id): RedirectResponse
    {
        $model   = new PaymentModel();
        $payment = $model->find($id);

        if ($payment === null) {
            return $this->back('app')->with('error', 'Payment મળ્યું નહીં.');
        }

        $model->delete($id);

        $target = $payment['appointment_id'] ? 'app/appointments/' . $payment['appointment_id'] : 'app/customers/' . $payment['customer_id'];

        return redirect()->to(site_url($target))->with('success', 'Payment દૂર કર્યું.');
    }
}
