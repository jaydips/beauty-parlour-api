<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\PaymentModel;
use App\Models\ReminderModel;
use App\Models\ServiceModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;

/**
 * GET /dashboard?date=Y-m-d (default today, local time)
 * One call for the app's home screen: the day's appointments, due reminders with wa.me links,
 * today's and this month's revenue, pending dues and a few counters.
 */
class Dashboard extends ApiController
{
    private const LIST_LIMIT = 20;

    public function index(): ResponseInterface
    {
        $date      = $this->dateParam('date');
        $day       = Time::parse($date . ' 00:00:00');
        $dayStart  = $day->toDateTimeString();
        $dayEnd    = $day->setTime(23, 59, 59)->toDateTimeString();
        $monthFrom = $day->setDay(1)->toDateTimeString();
        $monthTo   = $day->setDay((int) $day->format('t'))->setTime(23, 59, 59)->toDateTimeString();

        $appointments = (new AppointmentModel())
            ->where('starts_at >=', $dayStart)->where('starts_at <=', $dayEnd)
            ->orderBy('starts_at', 'ASC')->findAll();
        $appointments = service('appointments')->attachRelations($appointments);

        $counts = array_fill_keys(AppointmentModel::STATUSES, 0);
        foreach ($appointments as $a) {
            $counts[$a['status']] = ($counts[$a['status']] ?? 0) + 1;
        }
        $counts['total'] = count($appointments);

        $upcoming = (new AppointmentModel())
            ->whereIn('status', [AppointmentModel::STATUS_SCHEDULED, AppointmentModel::STATUS_CONFIRMED])
            ->where('starts_at >', $this->now())
            ->where('starts_at <=', Time::now()->addDays(7)->toDateTimeString())
            ->countAllResults();

        $payments = new PaymentModel();
        $dueCount = (new ReminderModel())->where('status', 'pending')->where('due_at <=', $dayEnd)->countAllResults();
        $dueRows  = (new ReminderModel())->where('status', 'pending')->where('due_at <=', $dayEnd)
            ->orderBy('due_at', 'ASC')->findAll(self::LIST_LIMIT);

        $dues = $this->pendingDues($payments);

        return $this->respond([
            'date'         => $date,
            'now'          => $this->now(),
            'parlour'      => ['name' => (new SettingModel())->value('parlour_name')],
            'appointments' => ['counts' => $counts, 'list' => $appointments],
            'upcoming_7_days' => $upcoming,
            'reminders_due'   => ['count' => $dueCount, 'list' => $this->decorateReminders($dueRows)],
            'revenue'      => [
                'today' => $this->revenue($payments, $dayStart, $dayEnd),
                'month' => $this->revenue($payments, $monthFrom, $monthTo),
            ],
            'dues'         => $dues,
            'counts'       => [
                'customers'       => (new CustomerModel())->countAllResults(),
                'active_services' => (new ServiceModel())->where('is_active', 1)->countAllResults(),
            ],
        ]);
    }

    /**
     * Payments minus refunds in a period, plus a per-method breakdown of payments.
     *
     * @return array{payments: int, refunds: int, net: int, count: int, by_method: array<string, int>}
     */
    private function revenue(PaymentModel $payments, string $from, string $to): array
    {
        $rows = $payments->select('type, method, SUM(amount) AS total, COUNT(*) AS cnt')
            ->where('paid_at >=', $from)->where('paid_at <=', $to)
            ->groupBy('type')->groupBy('method')->findAll();

        $out = ['payments' => 0, 'refunds' => 0, 'net' => 0, 'count' => 0, 'by_method' => []];

        foreach ($rows as $r) {
            $amount = (int) $r['total'];
            $out['count'] += (int) $r['cnt'];

            if ($r['type'] === PaymentModel::TYPE_REFUND) {
                $out['refunds'] += $amount;
            } else {
                $out['payments'] += $amount;
                $out['by_method'][$r['method']] = ($out['by_method'][$r['method']] ?? 0) + $amount;
            }
        }

        $out['net'] = $out['payments'] - $out['refunds'];

        return $out;
    }

    /**
     * Completed appointments whose total exceeds the net amount paid.
     *
     * @return array{count: int, total: int, list: list<array<string, mixed>>}
     */
    private function pendingDues(PaymentModel $payments): array
    {
        $completed = (new AppointmentModel())
            ->where('status', AppointmentModel::STATUS_COMPLETED)->where('total >', 0)
            ->orderBy('starts_at', 'DESC')->findAll(500);

        if ($completed === []) {
            return ['count' => 0, 'total' => 0, 'list' => []];
        }

        $paid = [];
        foreach ($payments->select('appointment_id, type, SUM(amount) AS total')
            ->whereIn('appointment_id', array_column($completed, 'id'))
            ->groupBy('appointment_id')->groupBy('type')->findAll() as $p) {
            $sign = $p['type'] === PaymentModel::TYPE_REFUND ? -1 : 1;
            $paid[(int) $p['appointment_id']] = ($paid[(int) $p['appointment_id']] ?? 0) + $sign * (int) $p['total'];
        }

        $list  = [];
        $total = 0;

        foreach ($completed as $a) {
            $balance = (int) $a['total'] - ($paid[(int) $a['id']] ?? 0);

            if ($balance > 0) {
                $total += $balance;
                $list[] = ['appointment_id' => (int) $a['id'], 'customer_id' => (int) $a['customer_id'], 'starts_at' => $a['starts_at'], 'total' => (int) $a['total'], 'paid' => $paid[(int) $a['id']] ?? 0, 'balance' => $balance];
            }
        }

        $count = count($list);
        $list  = array_slice($list, 0, self::LIST_LIMIT);

        if ($list !== []) {
            $customers = [];
            foreach ((new CustomerModel())->withDeleted()->whereIn('id', array_values(array_unique(array_column($list, 'customer_id'))))->findAll() as $c) {
                $customers[(int) $c['id']] = ['id' => (int) $c['id'], 'name' => $c['name'], 'phone' => $c['phone']];
            }
            foreach ($list as &$row) {
                $row['customer'] = $customers[$row['customer_id']] ?? null;
            }
        }

        return ['count' => $count, 'total' => $total, 'list' => $list];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function decorateReminders(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $wa        = service('whatsapp');
        $customers = [];

        foreach ((new CustomerModel())->withDeleted()->whereIn('id', array_values(array_unique(array_column($rows, 'customer_id'))))->findAll() as $c) {
            $customers[(int) $c['id']] = ['id' => (int) $c['id'], 'name' => $c['name'], 'phone' => $c['phone']];
        }

        foreach ($rows as &$row) {
            $customer        = $customers[(int) $row['customer_id']] ?? null;
            $row['customer'] = $customer;
            $row['wa_link']  = $customer ? $wa->link($customer['phone'], (string) $row['message']) : null;
        }

        return $rows;
    }
}
