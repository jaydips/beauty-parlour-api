<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Models\CustomerModel;
use App\Models\ReminderModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;

/**
 * WhatsApp reminders (manual click-to-chat) in the web UI.
 *   GET  /app/reminders?due=today|overdue|all&status=pending|sent|skipped|cancelled
 *   GET  /app/reminders/new        POST /app/reminders   (custom reminder)
 *   POST /app/reminders/{id}/sent   the owner sent the WhatsApp message themselves
 *   POST /app/reminders/{id}/skip   do not send this one
 */
class Reminders extends WebController
{
    public function index(): string
    {
        $due    = (string) ($this->request->getGet('due') ?? 'today');
        $status = (string) ($this->request->getGet('status') ?? 'pending');
        $model  = new ReminderModel();

        if (in_array($status, ReminderModel::STATUSES, true)) {
            $model->where('status', $status);
        } else {
            $status = 'all';
        }

        if ($due === 'today') {
            $model->where('due_at <=', Time::now()->setTime(23, 59, 59)->toDateTimeString());
        } elseif ($due === 'overdue') {
            $model->where('due_at <', Time::now()->toDateTimeString());
        } else {
            $due = 'all';
        }

        $rows = $model->orderBy('due_at', $status === 'pending' ? 'ASC' : 'DESC')->findAll(200);

        return $this->render('web/reminders/index', [
            'title'     => lang('App.title_reminders'),
            'due'       => $due,
            'status'    => $status,
            'reminders' => service('dashboard')->decorateReminders($rows),
        ], 'more');
    }

    public function create(): string
    {
        $customer = null;

        if ($cid = (int) $this->request->getGet('customer_id')) {
            $customer = (new CustomerModel())->find($cid);
        }

        return $this->render('web/reminders/form', [
            'title'    => lang('App.title_new_reminder'),
            'customer' => $customer,
            'date'     => old('date', Time::now()->toDateString()),
            'time'     => old('time', Time::now()->addHours(1)->format('H:00')),
        ], 'more');
    }

    public function store(): RedirectResponse
    {
        $data       = $this->input();
        $customerId = (int) ($data['customer_id'] ?? 0);
        $message    = trim((string) ($data['message'] ?? ''));
        $date       = (string) ($data['date'] ?? '');
        $time       = (string) ($data['time'] ?? '');

        if ($customerId <= 0 || (new CustomerModel())->find($customerId) === null) {
            return redirect()->back()->withInput()->with('error', lang('App.select_customer'));
        }
        if ($message === '') {
            return redirect()->back()->withInput()->with('error', lang('App.enter_message'));
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! preg_match('/^\d{2}:\d{2}$/', $time) || strtotime("{$date} {$time}:00") === false) {
            return redirect()->back()->withInput()->with('error', lang('App.fill_date_time'));
        }

        $model = new ReminderModel();
        $id    = $model->insert([
            'customer_id' => $customerId,
            'type'        => 'custom',
            'due_at'      => "{$date} {$time}:00",
            'channel'     => 'whatsapp',
            'message'     => mb_substr($message, 0, 2000),
            'status'      => 'pending',
        ]);

        if ($id === false) {
            return redirect()->back()->withInput()->with('error', implode(' ', $model->errors()));
        }

        return redirect()->to(site_url('app/reminders') . '?due=all')->with('success', lang('App.reminder_added'));
    }

    public function sent(int $id): RedirectResponse
    {
        $model = new ReminderModel();

        if ($model->find($id) === null) {
            return $this->back()->with('error', lang('App.reminder_not_found'));
        }

        $model->markSent($id);

        return $this->back()->with('success', lang('App.reminder_marked_sent'));
    }

    public function skip(int $id): RedirectResponse
    {
        $model = new ReminderModel();

        if ($model->find($id) === null) {
            return $this->back()->with('error', lang('App.reminder_not_found'));
        }

        $model->update($id, ['status' => 'skipped', 'sent_at' => null]);

        return $this->back()->with('success', lang('App.reminder_skipped'));
    }
}
