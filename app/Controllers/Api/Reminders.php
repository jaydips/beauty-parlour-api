<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\ReminderModel;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

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

        return $this->respond(['reminders' => service('reminders')->decorate($rows), 'pager' => $this->pagerInfo($model->pager)]);
    }

    public function show(int $id): ResponseInterface
    {
        $row = (new ReminderModel())->find($id);

        return $row === null ? $this->failNotFound('Reminder not found.') : $this->respond(['reminder' => service('reminders')->decorate([$row])[0]]);
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

        try {
            $id = service('reminders')->createCustom($data);
        } catch (InvalidArgumentException $e) {
            return $this->failValidationErrors(['customer_id' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->respondCreated(['reminder' => service('reminders')->decorate([(new ReminderModel())->find($id)])[0]]);
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
        if (! service('reminders')->setStatus($id, $status)) {
            return $this->failNotFound('Reminder not found.');
        }

        return $this->respond(['reminder' => service('reminders')->decorate([(new ReminderModel())->find($id)])[0]]);
    }
}
