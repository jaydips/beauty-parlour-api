<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\PaymentModel;
use CodeIgniter\I18n\Time;
use RuntimeException;

/**
 * Customer logic shared by the JSON API and the web UI: search, create-or-restore
 * (the hosting SQLite has no partial unique index, so a re-registered phone restores the
 * soft-deleted row), input normalisation and visit statistics.
 * Registered as the shared service `customers`.
 */
class CustomerService
{
    public const FIELDS = ['name', 'phone', 'alt_phone', 'gender', 'birthday', 'notes', 'whatsapp_opt_in'];

    public function __construct(
        private readonly CustomerModel $customers = new CustomerModel(),
        private readonly AppointmentModel $appointments = new AppointmentModel(),
        private readonly PaymentModel $payments = new PaymentModel(),
    ) {
    }

    /**
     * Name or phone search, paginated with the model's pager (use $model->pager afterwards).
     *
     * @return array{rows: list<array<string, mixed>>, pager: object}
     */
    public function search(string $q, int $page, int $perPage): array
    {
        $model = new CustomerModel();
        $q     = trim($q);

        if ($q !== '') {
            $model->groupStart()->like('name', $q)->orLike('phone', preg_replace('/\D+/', '', $q) ?: $q)->groupEnd();
        }

        $rows = $model->orderBy('name', 'ASC')->paginate($perPage, 'default', $page);

        return ['rows' => $rows, 'pager' => $model->pager];
    }

    /**
     * Quick lookup for autocomplete widgets: up to $limit customers matching name or phone.
     *
     * @return list<array{id: int, name: string, phone: string}>
     */
    public function suggest(string $q, int $limit = 8): array
    {
        $q = trim($q);

        if ($q === '') {
            $rows = $this->customers->orderBy('updated_at', 'DESC')->findAll($limit);
        } else {
            $rows = $this->customers->groupStart()->like('name', $q)->orLike('phone', preg_replace('/\D+/', '', $q) ?: $q)->groupEnd()
                ->orderBy('name', 'ASC')->findAll($limit);
        }

        return array_map(static fn (array $c): array => ['id' => (int) $c['id'], 'name' => $c['name'], 'phone' => $c['phone']], $rows);
    }

    /**
     * Keeps only customer fields and normalises phones (digits only) and the opt-in flag.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function pick(array $data): array
    {
        $out = array_intersect_key($data, array_flip(self::FIELDS));

        if (array_key_exists('whatsapp_opt_in', $out)) {
            $out['whatsapp_opt_in'] = filter_var($out['whatsapp_opt_in'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        foreach (['phone', 'alt_phone'] as $field) {
            if (isset($out[$field]) && is_string($out[$field])) {
                $out[$field] = preg_replace('/\D+/', '', $out[$field]);
            }
        }

        foreach (['alt_phone', 'gender', 'birthday', 'notes'] as $field) {
            if (array_key_exists($field, $out) && is_string($out[$field]) && trim($out[$field]) === '') {
                $out[$field] = null;
            }
        }

        return $out;
    }

    /**
     * Creates a customer, or restores and updates the soft-deleted row that owns the same phone.
     *
     * @param array<string, mixed> $data already picked (see pick())
     *
     * @return array{id: int, restored: bool}
     *
     * @throws RuntimeException with the validation messages joined when the model rejects the row
     */
    public function createOrRestore(array $data): array
    {
        $phone = (string) ($data['phone'] ?? '');

        if ($phone !== '') {
            $existing = $this->customers->findByPhone($phone, true);

            if ($existing !== null && $existing['deleted_at'] !== null) {
                $this->customers->restore((int) $existing['id']);
                $data['id'] = (int) $existing['id'];

                if (! $this->customers->save($data)) {
                    throw new RuntimeException(implode(' ', $this->customers->errors()));
                }

                return ['id' => (int) $existing['id'], 'restored' => true];
            }
        }

        $id = $this->customers->insert($data);

        if ($id === false) {
            throw new RuntimeException(implode(' ', $this->customers->errors()));
        }

        return ['id' => (int) $id, 'restored' => false];
    }

    /**
     * Validation errors of the last model operation (field => message).
     *
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->customers->errors() ?: [];
    }

    /**
     * Visits, last visit, total spent and the next few upcoming appointments.
     *
     * @return array<string, mixed>
     */
    public function stats(int $customerId): array
    {
        $completed = $this->appointments->where('customer_id', $customerId)->where('status', AppointmentModel::STATUS_COMPLETED)
            ->orderBy('starts_at', 'DESC')->findAll(50);
        $upcoming = $this->appointments->where('customer_id', $customerId)
            ->whereIn('status', [AppointmentModel::STATUS_SCHEDULED, AppointmentModel::STATUS_CONFIRMED])
            ->where('starts_at >=', Time::now()->toDateTimeString())->orderBy('starts_at', 'ASC')->findAll(5);

        $net = 0;

        foreach ($this->payments->select('type, SUM(amount) AS total')->where('customer_id', $customerId)->groupBy('type')->findAll() as $row) {
            $net += $row['type'] === PaymentModel::TYPE_REFUND ? -(int) $row['total'] : (int) $row['total'];
        }

        return [
            'visits'        => count($completed),
            'last_visit_at' => $completed[0]['starts_at'] ?? null,
            'total_spent'   => $net,
            'upcoming'      => array_map(static fn (array $a): array => ['id' => (int) $a['id'], 'starts_at' => $a['starts_at'], 'status' => $a['status'], 'total' => (int) $a['total']], $upcoming),
        ];
    }
}
