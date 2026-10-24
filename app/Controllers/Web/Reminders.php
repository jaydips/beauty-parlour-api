<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Models\CustomerModel;
use App\Models\ReminderModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use InvalidArgumentException;
use RuntimeException;

/**
 * WhatsApp to-do list in the browser.
 *
 *   GET  /app/reminders?due=today|overdue|all|sent&type=   list with WhatsApp / sent / skip buttons
 *   GET  /app/reminders/new?customer_id=                   custom reminder form
 *   POST /app/reminders                                    create custom reminder
 *   POST /app/reminders/{id}/status  {status, return}      sent | skipped | pending | cancelled
 */
class Reminders extends WebController
{
    private const PER_PAGE = 30;

    public function index(): string
    {
        $model = new ReminderModel();
        $due   = (string) ($this->request->getGet('due') ?? 'today');
        $type  = (string) ($this->request->getGet('type') ?? '');
        $page  = max(1, (int) ($this->request->getGet('page') ?? 1));
        $now   = Time::now()->toDateTimeString();

        switch ($due) {
            case 'overdue':
                $model->where('status', 'pending')->where('due_at <', $now)->orderBy('due_at', 'ASC');
                break;

            case 'all':
                $model->where('status', 'pending')->orderBy('due_at', 'ASC');
                break;

            case 'sent':
                $model->whereIn('status', ['sent', 'skipped', 'cancelled'])->orderBy('updated_at', 'DESC');
                break;

            default:
                $due = 'today';
                $model->where('status', 'pending')->where('due_at <=', Time::now()->toDateString() . ' 23:59:59')->orderBy('due_at', 'ASC');
        }

        if (in_array($type, ReminderModel::TYPES, true)) {
            $model->where('type', $type);
        } else {
            $type = '';
        }

        $rows = $model->paginate(self::PER_PAGE, 'default', $page);

        return $this->render('reminders/index', [
            'reminders' => service('reminders')->decorate($rows),
            'pager'     => $model->pager,
            'due'       => $due,
            'type'      => $type,
            'return'    => current_url(),
            'tab'       => 'more',
            'back'      => site_url('app/more'),
        ], 'રિમાઇન્ડર');
    }

    public function create(): string
    {
        $customer = null;

        if ($id = (int) $this->request->getGet('customer_id')) {
            $customer = (new CustomerModel())->find($id);
        }

        return $this->render('reminders/form', [
            'errors'   => [],
            'old'      => [
                'customer_id'    => $customer['id'] ?? '',
                'customer_label' => $customer ? $customer['name'] . ' · ' . $customer['phone'] : '',
                'due_date'       => Time::now()->toDateString(),
                'due_time'       => Time::now()->format('H:i'),
                'message'        => '',
                'appointment_id' => (int) $this->request->getGet('appointment_id') ?: '',
            ],
            'tab'  => 'more',
            'back' => site_url('app/reminders'),
        ], 'નવો રિમાઇન્ડર');
    }

    public function store(): ResponseInterface|string
    {
        $in     = $this->post(['customer_id', 'customer_label', 'due_date', 'due_time', 'message', 'appointment_id']);
        $errors = [];

        if ((int) $in['customer_id'] <= 0 || (new CustomerModel())->find((int) $in['customer_id']) === null) {
            $errors['customer_id'] = 'ગ્રાહક પસંદ કરો.';
        }

        if (trim((string) $in['message']) === '') {
            $errors['message'] = 'મેસેજ લખો.';
        }

        $dueAt = $this->combineDateTime((string) $in['due_date'], (string) $in['due_time']);

        if ($dueAt === null) {
            $errors['due_date'] = 'તારીખ અને સમય યોગ્ય નથી.';
        }

        if ($errors === []) {
            try {
                service('reminders')->createCustom([
                    'customer_id'    => (int) $in['customer_id'],
                    'appointment_id' => (int) $in['appointment_id'] ?: null,
                    'message'        => (string) $in['message'],
                    'due_at'         => $dueAt,
                ]);

                return $this->redirectWith('app/reminders?due=all', 'success', 'રિમાઇન્ડર ઉમેરાયો.');
            } catch (InvalidArgumentException|RuntimeException $e) {
                $errors['message'] = $e->getMessage();
            }
        }

        return $this->render('reminders/form', [
            'errors' => $errors,
            'old'    => $in,
            'tab'    => 'more',
            'back'   => site_url('app/reminders'),
        ], 'નવો રિમાઇન્ડર');
    }

    public function status(int $id): RedirectResponse
    {
        $status = (string) $this->request->getPost('status');
        $return = $this->returnTo('app');

        if (! in_array($status, ReminderModel::STATUSES, true)) {
            session()->setFlashdata('error', 'અમાન્ય સ્ટેટસ.');

            return redirect()->to($return);
        }

        if (! service('reminders')->setStatus($id, $status)) {
            session()->setFlashdata('error', 'રિમાઇન્ડર મળ્યો નહીં.');

            return redirect()->to($return);
        }

        $message = match ($status) {
            'sent'    => 'મોકલાઈ ગયું તરીકે નોંધ્યું.',
            'skipped' => 'રિમાઇન્ડર છોડી દીધો.',
            'pending' => 'રિમાઇન્ડર ફરી બાકીમાં મૂક્યો.',
            default   => 'રિમાઇન્ડર રદ કર્યો.',
        };

        session()->setFlashdata('success', $message);

        return redirect()->to($return);
    }

    /**
     * "Y-m-d" + "H:i" -> "Y-m-d H:i:00", or null when invalid.
     */
    private function combineDateTime(string $date, string $time): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
            return null;
        }

        $value = $date . ' ' . substr($time, 0, 5) . ':00';

        return strtotime($value) === false ? null : $value;
    }
}
