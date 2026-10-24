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
 * Home-screen data shared by the JSON API (GET /dashboard) and the web UI (GET /app):
 * the day's appointments, due reminders with wa.me links, revenue, pending dues and counters.
 * Registered as the shared service `dashboard`.
 *
 * Plain GROUP BY aggregates and PHP-side sums only (portable to SQLite 3.7.17 and MySQL).
 */
class DashboardService
{
    public const LIST_LIMIT = 20;

    public function __construct(
        private readonly AppointmentModel $appointments = new AppointmentModel(),
        private readonly PaymentModel $payments = new PaymentModel(),
        private readonly ReminderModel $reminders = new ReminderModel(),
        private readonly CustomerModel $customers = new CustomerModel(),
        private readonly ServiceModel $services = new ServiceModel(),
        private readonly SettingModel $settings = new SettingModel(),
    ) {
    }

    /**
     * Everything the home screen needs for one local calendar day.
     *
     * @return array<string, mixed>
     */
    public function data(string $date): array
    {
        $day       = Time::parse($date . ' 00:00:00');
        $dayStart  = $day->toDateTimeString();
        $dayEnd    = $day->setTime(23, 59, 59)->toDateTimeString();
        $monthFrom = $day->setDay(1)->toDateTimeString();
        $monthTo   = $day->setDay((int) $day->format('t'))->setTime(23, 59, 59)->toDateTimeString();
        $now       = Time::now()->toDateTimeString();

        $appointments = service('appointments')->attachRelations($this->appointments->forDay($date));

        $counts = array_fill_keys(AppointmentModel::STATUSES, 0);

        foreach ($appointments as $a) {
            $counts[$a['status']] = ($counts[$a['status']] ?? 0) + 1;
        }
        $counts['total'] = count($appointments);

        $upcoming = $this->appointments
            ->whereIn('status', [AppointmentModel::STATUS_SCHEDULED, AppointmentModel::STATUS_CONFIRMED])
            ->where('starts_at >', $now)
            ->where('starts_at <=', Time::now()->addDays(7)->toDateTimeString())
            ->countAllResults();

        $dueCount = $this->reminders->where('status', 'pending')->where('due_at <=', $dayEnd)->countAllResults();
        $dueRows  = $this->reminders->where('status', 'pending')->where('due_at <=', $dayEnd)
            ->orderBy('due_at', 'ASC')->findAll(self::LIST_LIMIT);

        return [
            'date'            => $date,
            'now'             => $now,
            'parlour'         => ['name' => $this->settings->value('parlour_name')],
            'appointments'    => ['counts' => $counts, 'list' => $appointments],
            'upcoming_7_days' => $upcoming,
            'reminders_due'   => ['count' => $dueCount, 'list' => service('reminders')->decorate($dueRows)],
            'revenue'         => [
                'today' => $this->revenue($dayStart, $dayEnd),
                'month' => $this->revenue($monthFrom, $monthTo),
            ],
            'dues'   => $this->pendingDues(self::LIST_LIMIT),
            'counts' => [
                'customers'       => $this->customers->countAllResults(),
                'active_services' => $this->services->where('is_active', 1)->countAllResults(),
            ],
        ];
    }

    /**
     * Payments minus refunds between two local timestamps, plus a per-method breakdown of payments.
     *
     * @return array{payments: int, refunds: int, net: int, count: int, by_method: array<string, int>}
     */
    public function revenue(string $from, string $to): array
    {
        $rows = $this->payments->select('type, method, SUM(amount) AS total, COUNT(*) AS cnt')
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
     * Completed appointments whose total exceeds the net amount paid, newest first.
     * `$limit` caps the returned list (count/total always cover every due).
     *
     * @return array{count: int, total: int, list: list<array<string, mixed>>}
     */
    public function pendingDues(?int $limit = null): array
    {
        $completed = $this->appointments
            ->where('status', AppointmentModel::STATUS_COMPLETED)->where('total >', 0)
            ->orderBy('starts_at', 'DESC')->findAll();

        if ($completed === []) {
            return ['count' => 0, 'total' => 0, 'list' => []];
        }

        $paid = [];

        foreach ($this->payments->select('appointment_id, type, SUM(amount) AS total')
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
                $list[] = [
                    'appointment_id' => (int) $a['id'],
                    'customer_id'    => (int) $a['customer_id'],
                    'starts_at'      => $a['starts_at'],
                    'total'          => (int) $a['total'],
                    'paid'           => $paid[(int) $a['id']] ?? 0,
                    'balance'        => $balance,
                ];
            }
        }

        $count = count($list);

        if ($limit !== null) {
            $list = array_slice($list, 0, $limit);
        }

        if ($list !== []) {
            $customers = $this->customerSummaries(array_column($list, 'customer_id'));

            foreach ($list as &$row) {
                $row['customer'] = $customers[$row['customer_id']] ?? null;
            }
        }

        return ['count' => $count, 'total' => $total, 'list' => $list];
    }

    /**
     * id => {id, name, phone} for a set of customer ids (soft-deleted ones included, history must render).
     *
     * @param list<int|string> $ids
     *
     * @return array<int, array{id: int, name: string, phone: string}>
     */
    public function customerSummaries(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        $out = [];

        foreach ($this->customers->withDeleted()->whereIn('id', $ids)->findAll() as $c) {
            $out[(int) $c['id']] = ['id' => (int) $c['id'], 'name' => $c['name'], 'phone' => $c['phone']];
        }

        return $out;
    }
}
