<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Models\ServiceModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Price list screens.
 *   GET /app/services            POST /app/services
 *   GET /app/services/new        GET  /app/services/{id}/edit
 *   POST /app/services/{id}      POST /app/services/{id}/delete (soft)
 */
class Services extends WebController
{
    public function index(): string
    {
        $rows = (new ServiceModel())->orderBy('is_active', 'DESC')->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll();

        return $this->render('web/services/index', ['title' => lang('App.title_services'), 'services' => $rows], 'more');
    }

    public function create(): string
    {
        return $this->render('web/services/form', ['title' => lang('App.title_new_service'), 'service' => null], 'more');
    }

    public function store(): RedirectResponse
    {
        $model = new ServiceModel();
        $id    = $model->insert($this->pick($this->input()));

        if ($id === false) {
            return redirect()->back()->withInput()->with('error', implode(' ', $model->errors()));
        }

        return redirect()->to(site_url('app/services'))->with('success', lang('App.service_added'));
    }

    public function edit(int $id): string|RedirectResponse
    {
        $service = (new ServiceModel())->find($id);

        if ($service === null) {
            return redirect()->to(site_url('app/services'))->with('error', lang('App.service_not_found'));
        }

        return $this->render('web/services/form', ['title' => lang('App.title_edit_service'), 'service' => $service], 'more');
    }

    public function update(int $id): RedirectResponse
    {
        $model = new ServiceModel();

        if ($model->find($id) === null) {
            return redirect()->to(site_url('app/services'))->with('error', lang('App.service_not_found'));
        }

        if (! $model->update($id, $this->pick($this->input()))) {
            return redirect()->back()->withInput()->with('error', implode(' ', $model->errors()));
        }

        return redirect()->to(site_url('app/services'))->with('success', lang('App.service_updated'));
    }

    public function delete(int $id): RedirectResponse
    {
        $model = new ServiceModel();

        if ($model->find($id) === null) {
            return redirect()->to(site_url('app/services'))->with('error', lang('App.service_not_found'));
        }

        $model->delete($id);

        return redirect()->to(site_url('app/services'))->with('success', lang('App.service_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function pick(array $data): array
    {
        return [
            'name'             => trim((string) ($data['name'] ?? '')),
            'category'         => trim((string) ($data['category'] ?? '')) ?: null,
            'duration_minutes' => (int) ($data['duration_minutes'] ?? 30),
            'price'            => (int) preg_replace('/\D+/', '', (string) ($data['price'] ?? '0')),
            'is_active'        => ! empty($data['is_active']) ? 1 : 0,
            'sort_order'       => (int) ($data['sort_order'] ?? 0),
            'notes'            => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }
}
