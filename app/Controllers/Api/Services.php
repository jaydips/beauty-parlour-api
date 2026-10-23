<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\ServiceModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Price list.
 *   GET    /services?include_inactive=1   POST /services
 *   GET    /services/{id}                 PUT  /services/{id}   DELETE /services/{id} (soft)
 */
class Services extends ApiController
{
    public function index(): ResponseInterface
    {
        $model = new ServiceModel();
        $rows  = $this->request->getGet('include_inactive')
            ? $model->orderBy('is_active', 'DESC')->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll()
            : $model->active();

        return $this->respond(['services' => $rows]);
    }

    public function show(int $id): ResponseInterface
    {
        $row = (new ServiceModel())->find($id);

        return $row === null ? $this->failNotFound('Service not found.') : $this->respond(['service' => $row]);
    }

    public function create(): ResponseInterface
    {
        $model = new ServiceModel();
        $id    = $model->insert($this->pick($this->input()));

        if ($id === false) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respondCreated(['service' => $model->find($id)]);
    }

    public function update(int $id): ResponseInterface
    {
        $model = new ServiceModel();

        if ($model->find($id) === null) {
            return $this->failNotFound('Service not found.');
        }

        $data = $this->pick($this->input());

        if ($data === []) {
            return $this->fail('Nothing to update.', 400);
        }

        if (! $model->update($id, $data)) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respond(['service' => $model->find($id)]);
    }

    public function delete(int $id): ResponseInterface
    {
        $model = new ServiceModel();

        if ($model->find($id) === null) {
            return $this->failNotFound('Service not found.');
        }

        $model->delete($id);

        return $this->respondDeleted(['deleted' => $id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pick(array $data): array
    {
        $out = array_intersect_key($data, array_flip(['name', 'category', 'duration_minutes', 'price', 'is_active', 'sort_order', 'notes']));

        if (array_key_exists('is_active', $out)) {
            $out['is_active'] = filter_var($out['is_active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        return $out;
    }
}
