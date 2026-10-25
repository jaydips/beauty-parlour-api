<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\PaymentModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Customers screens of the web UI.
 *   GET  /app/customers?q=&page=        search list
 *   GET  /app/customers/search?q=       JSON for the appointment form's autocomplete
 *   GET  /app/customers/new             form
 *   POST /app/customers                 create (re-registering a deleted phone restores it)
 *   GET  /app/customers/{id}            detail with statistics and appointments
 *   GET  /app/customers/{id}/edit       form
 *   POST /app/customers/{id}            update
 *   POST /app/customers/{id}/delete     soft delete
 */
class Customers extends WebController
{
    private const PER_PAGE = 25;

    public function index(): string
    {
        $model = new CustomerModel();
        $q     = trim((string) ($this->request->getGet('q') ?? ''));
        $page  = max(1, (int) ($this->request->getGet('page') ?? 1));

        if ($q !== '') {
            $model->groupStart()->like('name', $q)->orLike('phone', $q)->groupEnd();
        }

        $rows = $model->orderBy('name', 'ASC')->paginate(self::PER_PAGE, 'default', $page);

        return $this->render('web/customers/index', [
            'title'     => lang('App.title_customers'),
            'q'         => $q,
            'customers' => $rows,
            'pager'     => $model->pager,
        ], 'customers');
    }

    public function search(): ResponseInterface
    {
        $q = trim((string) ($this->request->getGet('q') ?? ''));

        if (mb_strlen($q) < 2) {
            return $this->response->setJSON([]);
        }

        $rows = (new CustomerModel())->select('id, name, phone')
            ->groupStart()->like('name', $q)->orLike('phone', $q)->groupEnd()
            ->orderBy('name', 'ASC')->findAll(10);

        return $this->response->setJSON($rows);
    }

    public function create(): string
    {
        return $this->render('web/customers/form', ['title' => lang('App.title_new_customer'), 'customer' => null], 'customers');
    }

    public function store(): RedirectResponse
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
                    return redirect()->back()->withInput()->with('error', implode(' ', $model->errors()));
                }

                return redirect()->to(site_url('app/customers/' . $existing['id']))->with('success', lang('App.customer_restored'));
            }
        }

        $id = $model->insert($data);

        if ($id === false) {
            return redirect()->back()->withInput()->with('error', implode(' ', $model->errors()));
        }

        return redirect()->to(site_url('app/customers/' . $id))->with('success', lang('App.customer_added'));
    }

    public function show(int $id): string|RedirectResponse
    {
        $customer = (new CustomerModel())->find($id);

        if ($customer === null) {
            return redirect()->to(site_url('app/customers'))->with('error', lang('App.customer_not_found'));
        }

        $appointments = new AppointmentModel();
        $recent       = $appointments->where('customer_id', $id)->orderBy('starts_at', 'DESC')->findAll(10);
        $completed    = $appointments->where('customer_id', $id)->where('status', AppointmentModel::STATUS_COMPLETED)->countAllResults();
        $lastVisit    = $appointments->where('customer_id', $id)->where('status', AppointmentModel::STATUS_COMPLETED)->orderBy('starts_at', 'DESC')->first();

        $net = 0;
        foreach ((new PaymentModel())->select('type, SUM(amount) AS total')->where('customer_id', $id)->groupBy('type')->findAll() as $row) {
            $net += $row['type'] === PaymentModel::TYPE_REFUND ? -(int) $row['total'] : (int) $row['total'];
        }

        return $this->render('web/customers/show', [
            'title'    => $customer['name'],
            'c'        => $customer,
            'recent'   => service('appointments')->attachRelations($recent),
            'stats'    => ['visits' => $completed, 'last_visit_at' => $lastVisit['starts_at'] ?? null, 'total_spent' => $net],
            'wa_link'  => service('whatsapp')->link($customer['phone'], ''),
        ], 'customers');
    }

    public function edit(int $id): string|RedirectResponse
    {
        $customer = (new CustomerModel())->find($id);

        if ($customer === null) {
            return redirect()->to(site_url('app/customers'))->with('error', lang('App.customer_not_found'));
        }

        return $this->render('web/customers/form', ['title' => lang('App.title_edit_customer'), 'customer' => $customer], 'customers');
    }

    public function update(int $id): RedirectResponse
    {
        $model = new CustomerModel();

        if ($model->find($id) === null) {
            return redirect()->to(site_url('app/customers'))->with('error', lang('App.customer_not_found'));
        }

        $data       = $this->pick($this->input());
        $data['id'] = $id;

        if (! $model->update($id, $data)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $model->errors()));
        }

        return redirect()->to(site_url('app/customers/' . $id))->with('success', lang('App.customer_updated'));
    }

    public function delete(int $id): RedirectResponse
    {
        $model = new CustomerModel();

        if ($model->find($id) === null) {
            return redirect()->to(site_url('app/customers'))->with('error', lang('App.customer_not_found'));
        }

        $model->delete($id);

        return redirect()->to(site_url('app/customers'))->with('success', lang('App.customer_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function pick(array $data): array
    {
        $out = array_intersect_key($data, array_flip(['name', 'phone', 'alt_phone', 'gender', 'birthday', 'notes']));

        $out['whatsapp_opt_in'] = ! empty($data['whatsapp_opt_in']) ? 1 : 0;

        foreach (['phone', 'alt_phone'] as $field) {
            if (isset($out[$field])) {
                $out[$field] = preg_replace('/\D+/', '', (string) $out[$field]);
            }
        }

        foreach (['alt_phone', 'gender', 'birthday', 'notes'] as $field) {
            if (isset($out[$field]) && trim((string) $out[$field]) === '') {
                $out[$field] = null;
            }
        }

        return $out;
    }
}
