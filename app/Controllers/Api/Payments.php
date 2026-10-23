<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\PaymentModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 *   GET    /payments?from=&to=&customer_id=&appointment_id=&page=
 *   POST   /payments  {appointment_id? | customer_id, amount, method, type?, paid_at?, reference?, notes?}
 *   GET    /payments/{id}
 *   DELETE /payments/{id}   (data-entry corrections)
 */
class Payments extends ApiController
{
    public function index(): ResponseInterface
    {
        $model = new PaymentModel();
        [$page, $perPage] = $this->pageParams();

        if ($this->request->getGet('from')) { $model->where('paid_at >=', $this->dateParam('from') . ' 00:00:00'); }
        if ($this->request->getGet('to'))   { $model->where('paid_at <=', $this->dateParam('to') . ' 23:59:59'); }
        if ($cid = (int) $this->request->getGet('customer_id'))    { $model->where('customer_id', $cid); }
        if ($aid = (int) $this->request->getGet('appointment_id')) { $model->where('appointment_id', $aid); }

        $rows = $model->orderBy('paid_at', 'DESC')->paginate($perPage, 'default', $page);

        return $this->respond(['payments' => $this->attachCustomers($rows), 'pager' => $this->pagerInfo($model->pager)]);
    }

    public function show(int $id): ResponseInterface
    {
        $row = (new PaymentModel())->find($id);

        return $row === null ? $this->failNotFound('Payment not found.') : $this->respond(['payment' => $this->attachCustomers([$row])[0]]);
    }

    public function create(): ResponseInterface
    {
        $data  = $this->input();
        $rules = [
            'appointment_id' => 'permit_empty|is_natural_no_zero',
            'customer_id'    => 'permit_empty|is_natural_no_zero',
            'amount'         => 'required|is_natural_no_zero',
            'method'         => 'required|in_list[cash,upi,card,other]',
            'type'           => 'permit_empty|in_list[payment,refund]',
            'paid_at'        => 'permit_empty|valid_date[Y-m-d H:i:s]',
            'reference'      => 'permit_empty|string|max_length[100]',
            'notes'          => 'permit_empty|string',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationFailure();
        }

        $appointment = null;

        if (! empty($data['appointment_id'])) {
            $appointment = (new AppointmentModel())->find((int) $data['appointment_id']);

            if ($appointment === null) {
                return $this->failValidationErrors(['appointment_id' => 'Appointment not found.']);
            }

            $data['customer_id'] = (int) $appointment['customer_id'];
        } elseif (empty($data['customer_id'])) {
            return $this->failValidationErrors(['customer_id' => 'customer_id or appointment_id is required.']);
        } elseif ((new CustomerModel())->find((int) $data['customer_id']) === null) {
            return $this->failValidationErrors(['customer_id' => 'Customer not found.']);
        }

        $model = new PaymentModel();
        $id    = $model->insert([
            'customer_id'    => (int) $data['customer_id'],
            'appointment_id' => $appointment ? (int) $appointment['id'] : null,
            'type'           => $data['type'] ?? PaymentModel::TYPE_PAYMENT,
            'method'         => $data['method'],
            'amount'         => (int) $data['amount'],
            'paid_at'        => $data['paid_at'] ?? $this->now(),
            'reference'      => $data['reference'] ?? null,
            'notes'          => $data['notes'] ?? null,
        ]);

        if ($id === false) {
            return $this->failValidationErrors($model->errors());
        }

        $payload = ['payment' => $model->find($id)];

        if ($appointment !== null) {
            $paid                   = $model->netPaidForAppointment((int) $appointment['id']);
            $payload['appointment'] = ['id' => (int) $appointment['id'], 'total' => (int) $appointment['total'], 'paid' => $paid, 'balance' => max(0, (int) $appointment['total'] - $paid)];
        }

        return $this->respondCreated($payload);
    }

    public function delete(int $id): ResponseInterface
    {
        $model = new PaymentModel();

        if ($model->find($id) === null) {
            return $this->failNotFound('Payment not found.');
        }

        $model->delete($id);

        return $this->respondDeleted(['deleted' => $id]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function attachCustomers(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids       = array_values(array_unique(array_column($rows, 'customer_id')));
        $customers = [];

        foreach ((new CustomerModel())->withDeleted()->whereIn('id', $ids)->findAll() as $c) {
            $customers[(int) $c['id']] = ['id' => (int) $c['id'], 'name' => $c['name'], 'phone' => $c['phone']];
        }

        foreach ($rows as &$row) {
            $row['customer'] = $customers[(int) $row['customer_id']] ?? null;
        }

        return $rows;
    }
}
