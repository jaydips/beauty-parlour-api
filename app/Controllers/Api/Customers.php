<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\PaymentModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 *   GET    /customers?q=&page=&per_page=   search by name or phone
 *   POST   /customers                      (re-registering a soft-deleted phone restores the old record)
 *   GET    /customers/{id}                 with visit statistics
 *   PUT    /customers/{id}
 *   DELETE /customers/{id}                 soft delete
 */
class Customers extends ApiController
{
    public function index(): ResponseInterface
    {
        $model = new CustomerModel();
        $q     = trim((string) ($this->request->getGet('q') ?? ''));
        [$page, $perPage] = $this->pageParams();

        if ($q !== '') {
            $model->groupStart()->like('name', $q)->orLike('phone', $q)->groupEnd();
        }

        $rows = $model->orderBy('name', 'ASC')->paginate($perPage, 'default', $page);

        return $this->respond(['customers' => $rows, 'pager' => $this->pagerInfo($model->pager)]);
    }

    public function show(int $id): ResponseInterface
    {
        $customer = (new CustomerModel())->find($id);

        if ($customer === null) {
            return $this->failNotFound('Customer not found.');
        }

        return $this->respond(['customer' => $customer + ['stats' => $this->stats($id)]]);
    }

    public function create(): ResponseInterface
    {
        $model = new CustomerModel();
        $data  = $this->pick($this->input());
        $phone = (string) ($data['phone'] ?? '');

        if ($phone !== '') {
            $existing = $model->findByPhone($phone, true);

            if ($existing !== null && $existing['deleted_at'] !== null) {
                $model->restore((int) $existing['id']);
                $data['id'] = (int) $existing['id'];

                if (! $model->save($data)) {
                    return $this->failValidationErrors($model->errors());
                }

                return $this->respond(['customer' => $model->find((int) $existing['id']), 'restored' => true]);
            }
        }

        $id = $model->insert($data);

        if ($id === false) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respondCreated(['customer' => $model->find($id)]);
    }

    public function update(int $id): ResponseInterface
    {
        $model = new CustomerModel();

        if ($model->find($id) === null) {
            return $this->failNotFound('Customer not found.');
        }

        $data = $this->pick($this->input());

        if ($data === []) {
            return $this->fail('Nothing to update.', 400);
        }

        $data['id'] = $id; // lets the is_unique rule ignore this row

        if (! $model->update($id, $data)) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respond(['customer' => $model->find($id)]);
    }

    public function delete(int $id): ResponseInterface
    {
        $model = new CustomerModel();

        if ($model->find($id) === null) {
            return $this->failNotFound('Customer not found.');
        }

        $model->delete($id);

        return $this->respondDeleted(['deleted' => $id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(int $customerId): array
    {
        $appointments = new AppointmentModel();
        $payments     = new PaymentModel();

        $completed = $appointments->where('customer_id', $customerId)->where('status', AppointmentModel::STATUS_COMPLETED)
            ->orderBy('starts_at', 'DESC')->findAll(50);
        $upcoming = $appointments->where('customer_id', $customerId)
            ->whereIn('status', [AppointmentModel::STATUS_SCHEDULED, AppointmentModel::STATUS_CONFIRMED])
            ->where('starts_at >=', $this->now())->orderBy('starts_at', 'ASC')->findAll(5);

        $net = 0;

        foreach ($payments->select('type, SUM(amount) AS total')->where('customer_id', $customerId)->groupBy('type')->findAll() as $row) {
            $net += $row['type'] === PaymentModel::TYPE_REFUND ? -(int) $row['total'] : (int) $row['total'];
        }

        return [
            'visits'        => count($completed),
            'last_visit_at' => $completed[0]['starts_at'] ?? null,
            'total_spent'   => $net,
            'upcoming'      => array_map(static fn (array $a): array => ['id' => $a['id'], 'starts_at' => $a['starts_at'], 'status' => $a['status'], 'total' => $a['total']], $upcoming),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pick(array $data): array
    {
        $out = array_intersect_key($data, array_flip(['name', 'phone', 'alt_phone', 'gender', 'birthday', 'notes', 'whatsapp_opt_in']));

        if (array_key_exists('whatsapp_opt_in', $out)) {
            $out['whatsapp_opt_in'] = filter_var($out['whatsapp_opt_in'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        foreach (['phone', 'alt_phone'] as $field) {
            if (isset($out[$field]) && is_string($out[$field])) {
                $out[$field] = preg_replace('/\D+/', '', $out[$field]);
            }
        }

        return $out;
    }
}
