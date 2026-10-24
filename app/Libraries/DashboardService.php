<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\PaymentModel;
use App\Models\ReminderModel;
use App\Models\ServiceModel;
use App\Models\SettingModel;
use CodeIgniter\I18n\Time;

/**
 * Builds the home-screen data shared by GET /dashboard (JSON API) and /app (web UI):
 * the day's appointments, due reminders with wa.me links, revenue, pending dues, counters.
 * Plain GROUP BY / PHP-side aggregates (portable to SQLite 3.7.17 and MySQL).
 */
class DashboardService
{
    public const LIST_LIMIT = 20;

    /**
     * @return array<string, mixed>
     */
    public function build(string $date): array
    {
        $day       = Time::parse($date . ' 00:00:00');
        $dayStart  = $day->toDateTimeString();
        $dayEnd    = $day->setTime(23, 59, 59)->toDateTimeString();
        $monthFrom = $day->setDay(1)->toDateTimeString();
        $monthTo   = $day->setDay((int) $day->format('t'))->setTime(23, 59, 59)->toDateTimeString();
        $now       = Time::now()->toDateTimeString();

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
            ->where('starts_at >', $now)
            ->where('starts_at <=', Time::now()->addDays(7)->toDateTimeString())
            ->countAllResults();

        $payments = new PaymentModel();
        $dueCount = (new ReminderModel())->where('status', 'pending')->where('due_at <=', $dayEnd)->countAllResults();
        $dueRows  = (new ReminderModel())->where('status', 'pending')->where('due_at <=', $dayEnd)
            ->orderBy('due_at', 'ASC')->findAll(self::LIST_LIMIT);

        return [
            'date'            => $date,
            'now'             => $now,
            'parlour'         => ['name' => (new SettingModel())->value('parlour_name')],
            'appointments'    => ['counts' => $counts, 'list' => $appointments],
            'upcoming_7_days' => $upcoming,
            'reminders_due'   => ['count' => $dueCount, 'list' => $this->decorateReminders($dueRows)],
            'revenue'         => [
                'today' => $this->revenue($payments, $dayStart, $dayEnd),
                'month' => $this->revenue($payments, $monthFrom, $monthTo),
            ],
            'dues'            => $this->pendingDues($payments, self::LIST_LIMIT),
            'counts'          => [
                'customers'       => (new CustomerModel())->countAllResults(),
                'active_services' => (new ServiceModel())->where('is_active', 1)->countAllResults(),
            ],
        ];
    }

    /**
     * Payments minus refunds in a period, plus a per-method breakdown of payments.
     *
     * @return array{payments: int, refunds: int, net: int, count: int, by_method: array<string, int>}
     */
    public function revenue(PaymentModel $payments, string $from, string $to): array
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
    public function pendingDues(PaymentModel $payments, ?int $limit = null): array
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

        if ($limit !== null) {
            $list = array_slice($list, 0, $limit);
        }

        if ($list !== []) {
            $customers = $this->customerMap(array_column($list, 'customer_id'));
            foreach ($list as &$row) {
                $row['customer'] = $customers[$row['customer_id']] ?? null;
            }
        }

        return ['count' => $count, 'total' => $total, 'list' => $list];
    }

    /**
     * Adds customer and wa.me link to reminder rows.
     *
     * @param list<array<string, mixed>> $rows
     */
    public function decorateReminders(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $wa        = service('whatsapp');
        $customers = $this->customerMap(array_column($rows, 'customer_id'));

        foreach ($rows as &$row) {
            $customer        = $customers[(int) $row['customer_id']] ?? null;
            $row['customer'] = $customer;
            $row['wa_link']  = $customer ? $wa->link($customer['phone'], (string) $row['message']) : null;
        }

        return $rows;
    }

    /**
     * @param list<int|string> $ids
     *
     * @return array<int, array{id: int, name: string, phone: string}>
     */
    private function customerMap(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        $map = [];

        foreach ((new CustomerModel())->withDeleted()->whereIn('id', $ids)->findAll() as $c) {
            $map[(int) $c['id']] = ['id' => (int) $c['id'], 'name' => $c['name'], 'phone' => $c['phone']];
        }

        return $map;
    }
}
