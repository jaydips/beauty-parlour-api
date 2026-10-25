<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\ReminderModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * The owner's WhatsApp to-do list. Every item carries `wa_link`: tapping it opens the owner's own
 * WhatsApp with the message prefilled; the owner sends it and then marks the reminder as sent.
 *
 *   GET    /reminders?status=pending&due=today|overdue|all|Y-m-d&type=&page=
 *   POST   /reminders   {customer_id, message, due_at, appointment_id?}   (type "custom")
 *   GET    /reminders/{id}
 *   PATCH  /reminders/{id}        {status: sent|skipped|cancelled|pending}
 *   POST   /reminders/{id}/sent   shortcut for status=sent
 */
class Reminders extends ApiController
{
    public function index(): ResponseInterface
    {
        $model = new ReminderModel();
        [$page, $perPage] = $this->pageParams();

        $status = (string) ($this->request->getGet('status') ?? 'pending');
        if ($status !== 'all') {
            $model->whereIn('status', array_intersect(explode(',', $status), ReminderModel::STATUSES) ?: ['pending']);
        }

        $due = (string) ($this->request->getGet('due') ?? 'today');
        if ($due === 'today') {
            $model->where('due_at <=', $this->dateParam('date') . ' 23:59:59');
        } elseif ($due === 'overdue') {
            $model->where('due_at <', $this->now());
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
            $model->where('due_at >=', $due . ' 00:00:00')->where('due_at <=', $due . ' 23:59:59');
        }

        if ($type = $this->request->getGet('type')) {
            $model->whereIn('type', array_intersect(explode(',', (string) $type), ReminderModel::TYPES) ?: ReminderModel::TYPES);
        }

        $rows = $model->orderBy('due_at', 'ASC')->paginate($perPage, 'default', $page);

        return $this->respond(['reminders' => $this->decorate($rows), 'pager' => $this->pagerInfo($model->pager)]);
    }

    public function show(int $id): ResponseInterface
    {
        $row = (new ReminderModel())->find($id);

        return $row === null ? $this->failNotFound(lang('App.reminder_not_found')) : $this->respond(['reminder' => $this->decorate([$row])[0]]);
    }

    public function create(): ResponseInterface
    {
        $data  = $this->input();
        $rules = [
            'customer_id'    => 'required|is_natural_no_zero',
            'appointment_id' => 'permit_empty|is_natural_no_zero',
            'message'        => 'required|string|max_length[2000]',
            'due_at'         => 'required|valid_date[Y-m-d H:i:s]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationFailure();
        }

        if ((new CustomerModel())->find((int) $data['customer_id']) === null) {
            return $this->failValidationErrors(['customer_id' => lang('App.customer_not_found')]);
        }

        $model = new ReminderModel();
        $id    = $model->insert([
            'customer_id'    => (int) $data['customer_id'],
            'appointment_id' => ! empty($data['appointment_id']) ? (int) $data['appointment_id'] : null,
            'type'           => 'custom',
            'due_at'         => $data['due_at'],
            'channel'        => 'whatsapp',
            'message'        => $data['message'],
            'status'         => 'pending',
        ]);

        if ($id === false) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respondCreated(['reminder' => $this->decorate([$model->find($id)])[0]]);
    }

    public function updateStatus(int $id): ResponseInterface
    {
        $data  = $this->input();
        $rules = ['status' => 'required|in_list[pending,sent,skipped,cancelled]'];

        if (! $this->validateData($data, $rules)) {
            return $this->validationFailure();
        }

        return $this->setStatus($id, $data['status']);
    }

    public function markSent(int $id): ResponseInterface
    {
        return $this->setStatus($id, 'sent');
    }

    private function setStatus(int $id, string $status): ResponseInterface
    {
        $model = new ReminderModel();

        if ($model->find($id) === null) {
            return $this->failNotFound(lang('App.reminder_not_found'));
        }

        if ($status === 'sent') {
            $model->markSent($id);
        } else {
            $model->update($id, ['status' => $status, 'sent_at' => null]);
        }

        return $this->respond(['reminder' => $this->decorate([$model->find($id)])[0]]);
    }

    /**
     * Adds customer, appointment summary and the click-to-chat link.
     *
     * @param list<array<string, mixed>> $rows
     */
    private function decorate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $wa        = service('whatsapp');
        $customers = [];
        $appts     = [];

        foreach ((new CustomerModel())->withDeleted()->whereIn('id', array_values(array_unique(array_column($rows, 'customer_id'))))->findAll() as $c) {
            $customers[(int) $c['id']] = ['id' => (int) $c['id'], 'name' => $c['name'], 'phone' => $c['phone'], 'whatsapp_opt_in' => (int) $c['whatsapp_opt_in']];
        }

        $apptIds = array_values(array_filter(array_unique(array_column($rows, 'appointment_id'))));

        if ($apptIds !== []) {
            foreach ((new AppointmentModel())->whereIn('id', $apptIds)->findAll() as $a) {
                $appts[(int) $a['id']] = ['id' => (int) $a['id'], 'starts_at' => $a['starts_at'], 'status' => $a['status'], 'total' => (int) $a['total']];
            }
        }

        foreach ($rows as &$row) {
            $customer           = $customers[(int) $row['customer_id']] ?? null;
            $row['customer']    = $customer;
            $row['appointment'] = $row['appointment_id'] ? ($appts[(int) $row['appointment_id']] ?? null) : null;
            $row['wa_link']     = $customer ? $wa->link($customer['phone'], (string) $row['message']) : null;
        }

        return $rows;
    }
}
