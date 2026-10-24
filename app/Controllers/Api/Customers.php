<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\CustomerModel;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 *   GET    /customers?q=&page=&per_page=   search by name or phone
 *   POST   /customers                      (re-registering a soft-deleted phone restores the old record)
 *   GET    /customers/{id}                 with visit statistics
 *   PUT    /customers/{id}
 *   DELETE /customers/{id}                 soft delete
 *
 * Logic lives in App\Libraries\CustomerService (shared with the web UI).
 */
class Customers extends ApiController
{
    public function index(): ResponseInterface
    {
        [$page, $perPage] = $this->pageParams();
        $result           = service('customers')->search((string) ($this->request->getGet('q') ?? ''), $page, $perPage);

        return $this->respond(['customers' => $result['rows'], 'pager' => $this->pagerInfo($result['pager'])]);
    }

    public function show(int $id): ResponseInterface
    {
        $customer = (new CustomerModel())->find($id);

        if ($customer === null) {
            return $this->failNotFound('Customer not found.');
        }

        return $this->respond(['customer' => $customer + ['stats' => service('customers')->stats($id)]]);
    }

    public function create(): ResponseInterface
    {
        $svc  = service('customers');
        $data = $svc->pick($this->input());

        try {
            $result = $svc->createOrRestore($data);
        } catch (RuntimeException) {
            return $this->failValidationErrors($svc->errors() ?: ['phone' => 'Invalid customer data.']);
        }

        $customer = (new CustomerModel())->find($result['id']);

        return $result['restored']
            ? $this->respond(['customer' => $customer, 'restored' => true])
            : $this->respondCreated(['customer' => $customer]);
    }

    public function update(int $id): ResponseInterface
    {
        $model = new CustomerModel();

        if ($model->find($id) === null) {
            return $this->failNotFound('Customer not found.');
        }

        $data = service('customers')->pick($this->input());

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
}
