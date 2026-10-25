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
    public function index(): string
    {
        $today = Time::now();
        $from  = $this->validDate((string) ($this->request->getGet('from') ?? ''), $today->setDay(1)->toDateString());
        $to    = $this->validDate((string) ($this->request->getGet('to') ?? ''), $today->toDateString());

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $model = new PaymentModel();
        $rows  = $model->where('paid_at >=', $from . ' 00:00:00')->where('paid_at <=', $to . ' 23:59:59')
            ->orderBy('paid_at', 'DESC')->findAll(500);

        $totals = service('dashboard')->revenue(new PaymentModel(), $from . ' 00:00:00', $to . ' 23:59:59');

        $customers = [];
        if ($rows !== []) {
            foreach ((new CustomerModel())->withDeleted()->whereIn('id', array_values(array_unique(array_column($rows, 'customer_id'))))->findAll() as $c) {
                $customers[(int) $c['id']] = $c;
            }
        }
        foreach ($rows as &$row) {
            $row['customer'] = $customers[(int) $row['customer_id']] ?? null;
        }

        return $this->render('web/payments/index', [
            'title'    => lang('App.title_payments'),
            'from'     => $from,
            'to'       => $to,
            'payments' => $rows,
            'totals'   => $totals,
        ], 'more');
    }

    private function validDate(string $date, string $default): string
    {
        return (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false) ? $date : $default;
    }

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
            return $this->back('app')->with('error', lang('App.appointment_not_found'));
        }

        if ($customerId <= 0 || (new CustomerModel())->find($customerId) === null) {
            return $this->back('app')->with('error', lang('App.customer_not_found'));
        }
        if ($amount <= 0) {
            return $this->back('app')->with('error', lang('App.amount_min'));
        }
        if (! in_array($method, PaymentModel::METHODS, true)) {
            return $this->back('app')->with('error', lang('App.payment_method_invalid'));
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

        return redirect()->to(site_url($target))->with('success', lang('App.payment_recorded', [$type === 'refund' ? lang('App.refund') : lang('App.payment'), rupees($amount)]));
    }

    public function delete(int $id): RedirectResponse
    {
        $model   = new PaymentModel();
        $payment = $model->find($id);

        if ($payment === null) {
            return $this->back('app')->with('error', lang('App.payment_not_found'));
        }

        $model->delete($id);

        $target = $payment['appointment_id'] ? 'app/appointments/' . $payment['appointment_id'] : 'app/customers/' . $payment['customer_id'];

        return redirect()->to(site_url($target))->with('success', lang('App.payment_deleted'));
    }
}
