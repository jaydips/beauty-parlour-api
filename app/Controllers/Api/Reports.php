<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\AppointmentModel;
use App\Models\AppointmentServiceModel;
use App\Models\CustomerModel;
use App\Models\PaymentModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;

/**
 * Plain GROUP BY / PHP-side aggregates (portable across SQLite 3.7.17 and MySQL).
 *   GET /reports/revenue?from=&to=&group=day|month
 *   GET /reports/services?from=&to=          top services from completed appointments
 *   GET /reports/dues                        all completed appointments with a balance
 */
class Reports extends ApiController
{
    public function revenue(): ResponseInterface
    {
        [$from, $to] = $this->range();
        $group = $this->request->getGet('group') === 'month' ? 'month' : 'day';

        $rows = (new PaymentModel())->select('paid_at, type, method, amount')
            ->where('paid_at >=', $from . ' 00:00:00')->where('paid_at <=', $to . ' 23:59:59')
            ->orderBy('paid_at', 'ASC')->findAll();

        $series = [];
        $totals = ['payments' => 0, 'refunds' => 0, 'net' => 0, 'count' => 0, 'by_method' => []];

        foreach ($rows as $r) {
            $bucket = substr((string) $r['paid_at'], 0, $group === 'month' ? 7 : 10);
            $series[$bucket] ??= ['period' => $bucket, 'payments' => 0, 'refunds' => 0, 'net' => 0, 'count' => 0];
            $amount = (int) $r['amount'];
            $series[$bucket]['count']++;
            $totals['count']++;

            if ($r['type'] === PaymentModel::TYPE_REFUND) {
                $series[$bucket]['refunds'] += $amount;
                $totals['refunds'] += $amount;
            } else {
                $series[$bucket]['payments'] += $amount;
                $totals['payments'] += $amount;
                $totals['by_method'][$r['method']] = ($totals['by_method'][$r['method']] ?? 0) + $amount;
            }

            $series[$bucket]['net'] = $series[$bucket]['payments'] - $series[$bucket]['refunds'];
        }

        $totals['net'] = $totals['payments'] - $totals['refunds'];

        return $this->respond(['from' => $from, 'to' => $to, 'group' => $group, 'series' => array_values($series), 'totals' => $totals]);
    }

    public function services(): ResponseInterface
    {
        [$from, $to] = $this->range();

        $items = (new AppointmentServiceModel())->select('service_name, SUM(quantity) AS quantity, SUM(price * quantity) AS amount, COUNT(DISTINCT appointment_id) AS appointments')
            ->whereIn('appointment_id', static function ($builder) use ($from, $to) {
                return $builder->select('id')->from('appointments')
                    ->where('status', AppointmentModel::STATUS_COMPLETED)
                    ->where('starts_at >=', $from . ' 00:00:00')->where('starts_at <=', $to . ' 23:59:59');
            })
            ->groupBy('service_name')->orderBy('amount', 'DESC')->findAll();

        foreach ($items as &$row) {
            $row['quantity']     = (int) $row['quantity'];
            $row['amount']       = (int) $row['amount'];
            $row['appointments'] = (int) $row['appointments'];
        }

        return $this->respond(['from' => $from, 'to' => $to, 'services' => $items]);
    }

    public function dues(): ResponseInterface
    {
        $payments  = new PaymentModel();
        $completed = (new AppointmentModel())->where('status', AppointmentModel::STATUS_COMPLETED)->where('total >', 0)
            ->orderBy('starts_at', 'DESC')->findAll();

        $paid = [];
        if ($completed !== []) {
            foreach ($payments->select('appointment_id, type, SUM(amount) AS total')
                ->whereIn('appointment_id', array_column($completed, 'id'))
                ->groupBy('appointment_id')->groupBy('type')->findAll() as $p) {
                $sign = $p['type'] === PaymentModel::TYPE_REFUND ? -1 : 1;
                $paid[(int) $p['appointment_id']] = ($paid[(int) $p['appointment_id']] ?? 0) + $sign * (int) $p['total'];
            }
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

        if ($list !== []) {
            $customers = [];
            foreach ((new CustomerModel())->withDeleted()->whereIn('id', array_values(array_unique(array_column($list, 'customer_id'))))->findAll() as $c) {
                $customers[(int) $c['id']] = ['id' => (int) $c['id'], 'name' => $c['name'], 'phone' => $c['phone']];
            }
            foreach ($list as &$row) {
                $row['customer'] = $customers[$row['customer_id']] ?? null;
            }
        }

        return $this->respond(['count' => count($list), 'total' => $total, 'dues' => $list]);
    }

    /**
     * from/to query parameters, defaulting to the current month to date.
     *
     * @return array{0: string, 1: string}
     */
    private function range(): array
    {
        $today = Time::now();
        $from  = $this->dateParam('from', $today->setDay(1)->toDateString());
        $to    = $this->dateParam('to', $today->toDateString());

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
