<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\AppointmentModel;
use App\Models\AppointmentServiceModel;
use App\Models\CustomerModel;
use App\Models\PaymentModel;
use App\Models\ReminderModel;
use App\Models\ServiceModel;
use App\Models\SettingModel;
use CodeIgniter\I18n\Time;
use Config\Database;
use InvalidArgumentException;
use RuntimeException;

/**
 * Appointment domain logic shared by the API controllers:
 * line items with price snapshots, totals, duration, double-booking checks,
 * WhatsApp reminder scheduling and status transitions.
 *
 * Amounts are whole rupees; times are local (Asia/Kolkata) "Y-m-d H:i:s".
 */
class AppointmentService
{
    public const MIN_DURATION_MINUTES = 15;

    public function __construct(
        private readonly AppointmentModel $appointments = new AppointmentModel(),
        private readonly AppointmentServiceModel $items = new AppointmentServiceModel(),
        private readonly ServiceModel $services = new ServiceModel(),
        private readonly CustomerModel $customers = new CustomerModel(),
        private readonly PaymentModel $payments = new PaymentModel(),
        private readonly ReminderModel $reminders = new ReminderModel(),
        private readonly SettingModel $settings = new SettingModel(),
    ) {
    }

    /**
     * Turns request items into snapshot rows.
     * Each item is {service_id, quantity?} or a custom {service_name, price, duration_minutes, quantity?}.
     *
     * @param list<array<string, mixed>> $input
     *
     * @return list<array{service_id: ?int, service_name: string, duration_minutes: int, price: int, quantity: int}>
     *
     * @throws InvalidArgumentException on an unknown service or malformed item
     */
    public function buildItems(array $input): array
    {
        if ($input === []) {
            throw new InvalidArgumentException('At least one service item is required.');
        }

        $rows = [];

        foreach (array_values($input) as $i => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException("Item {$i} must be an object.");
            }

            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if (! empty($item['service_id'])) {
                $service = $this->services->withDeleted()->find((int) $item['service_id']);

                if ($service === null) {
                    throw new InvalidArgumentException("Item {$i}: service {$item['service_id']} does not exist.");
                }

                $rows[] = [
                    'service_id'       => (int) $service['id'],
                    'service_name'     => (string) $service['name'],
                    'duration_minutes' => (int) $service['duration_minutes'],
                    'price'            => isset($item['price']) ? max(0, (int) $item['price']) : (int) $service['price'],
                    'quantity'         => $quantity,
                ];

                continue;
            }

            $name = trim((string) ($item['service_name'] ?? ''));

            if ($name === '' || ! isset($item['price'])) {
                throw new InvalidArgumentException("Item {$i}: service_id, or service_name and price, is required.");
            }

            $rows[] = [
                'service_id'       => null,
                'service_name'     => mb_substr($name, 0, 100),
                'duration_minutes' => max(0, (int) ($item['duration_minutes'] ?? 30)),
                'price'            => max(0, (int) $item['price']),
                'quantity'         => $quantity,
            ];
        }

        return $rows;
    }

    /**
     * @return array{subtotal: int, discount: int, total: int}
     */
    public function totals(array $items, int $discount): array
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $subtotal += (int) $item['price'] * (int) $item['quantity'];
        }

        $discount = max(0, min($discount, $subtotal));

        return ['subtotal' => $subtotal, 'discount' => $discount, 'total' => $subtotal - $discount];
    }

    public function endsAt(string $startsAt, array $items): string
    {
        $minutes = 0;

        foreach ($items as $item) {
            $minutes += (int) $item['duration_minutes'] * (int) $item['quantity'];
        }

        return Time::parse($startsAt)->addMinutes(max(self::MIN_DURATION_MINUTES, $minutes))->toDateTimeString();
    }

    /**
     * Appointments that would double-book the slot.
     */
    public function conflicts(string $startsAt, string $endsAt, ?int $excludeId = null): array
    {
        return $this->appointments->findOverlapping($startsAt, $endsAt, $excludeId);
    }

    /**
     * Creates an appointment with items, totals and (if opted in) a WhatsApp reminder.
     *
     * @param array<string, mixed> $data  customer_id, starts_at, discount?, notes?, source?, status?
     * @param list<array<string, mixed>> $itemsInput
     *
     * @return array{id: int}
     *
     * @throws RuntimeException when the model rejects the row
     */
    public function create(array $data, array $itemsInput): array
    {
        $items  = $this->buildItems($itemsInput);
        $totals = $this->totals($items, (int) ($data['discount'] ?? 0));
        $row    = [
            'customer_id' => (int) $data['customer_id'],
            'starts_at'   => $data['starts_at'],
            'ends_at'     => $this->endsAt($data['starts_at'], $items),
            'status'      => $data['status'] ?? AppointmentModel::STATUS_SCHEDULED,
            'source'      => $data['source'] ?? null,
            'notes'       => $data['notes'] ?? null,
        ] + $totals;

        $db = Database::connect();
        $db->transStart();

        $id = $this->appointments->insert($row);

        if ($id === false) {
            $db->transRollback();

            throw new RuntimeException(implode(' ', $this->appointments->errors()));
        }

        foreach ($items as $item) {
            $this->items->insert(['appointment_id' => $id] + $item);
        }

        $this->scheduleAppointmentReminder((int) $id);

        $db->transComplete();

        return ['id' => (int) $id];
    }

    /**
     * Updates time, items, discount, notes or source. Items are replaced when given.
     *
     * @param list<array<string, mixed>>|null $itemsInput
     */
    public function update(int $id, array $data, ?array $itemsInput): void
    {
        $current = $this->appointments->find($id);

        if ($current === null) {
            throw new InvalidArgumentException('Appointment not found.');
        }

        $items    = $itemsInput !== null ? $this->buildItems($itemsInput) : $this->items->forAppointment($id);
        $startsAt = $data['starts_at'] ?? $current['starts_at'];
        $discount = (int) ($data['discount'] ?? $current['discount']);
        $totals   = $this->totals($items, $discount);

        $row = [
            'starts_at' => $startsAt,
            'ends_at'   => $this->endsAt($startsAt, $items),
            'notes'     => array_key_exists('notes', $data) ? $data['notes'] : $current['notes'],
            'source'    => array_key_exists('source', $data) ? $data['source'] : $current['source'],
        ] + $totals;

        if (! empty($data['customer_id'])) {
            $row['customer_id'] = (int) $data['customer_id'];
        }

        $db = Database::connect();
        $db->transStart();

        if (! $this->appointments->update($id, $row)) {
            $db->transRollback();

            throw new RuntimeException(implode(' ', $this->appointments->errors()));
        }

        if ($itemsInput !== null) {
            $this->items->where('appointment_id', $id)->delete();

            foreach ($items as $item) {
                $this->items->insert(['appointment_id' => $id] + $item);
            }
        }

        if ($startsAt !== $current['starts_at'] || $itemsInput !== null) {
            $this->reminders->where('appointment_id', $id)->where('type', 'appointment')->where('status', 'pending')->delete();
            $this->scheduleAppointmentReminder($id);
        }

        $db->transComplete();
    }

    /**
     * Status transition with side effects on reminders.
     */
    public function changeStatus(int $id, string $status, ?string $reason = null): void
    {
        if (! in_array($status, AppointmentModel::STATUSES, true)) {
            throw new InvalidArgumentException('Unknown status.');
        }

        $current = $this->appointments->find($id);

        if ($current === null) {
            throw new InvalidArgumentException('Appointment not found.');
        }

        $row = ['status' => $status];

        if (in_array($status, [AppointmentModel::STATUS_CANCELLED, AppointmentModel::STATUS_NO_SHOW], true)) {
            $row['cancelled_at']  = Time::now()->toDateTimeString();
            $row['cancel_reason'] = $reason !== null ? mb_substr($reason, 0, 255) : null;
        } else {
            $row['cancelled_at']  = null;
            $row['cancel_reason'] = null;
        }

        $db = Database::connect();
        $db->transStart();

        $this->appointments->update($id, $row);

        $stamp = Time::now()->toDateTimeString();

        if (in_array($status, [AppointmentModel::STATUS_CANCELLED, AppointmentModel::STATUS_NO_SHOW], true)) {
            $this->reminders->builder()->where('appointment_id', $id)->where('status', 'pending')
                ->update(['status' => 'cancelled', 'updated_at' => $stamp]);
        }

        if ($status === AppointmentModel::STATUS_COMPLETED) {
            $this->reminders->builder()->where('appointment_id', $id)->where('type', 'appointment')->where('status', 'pending')
                ->update(['status' => 'skipped', 'updated_at' => $stamp]);
            $this->scheduleFollowUp($id);
        }

        $db->transComplete();
    }

    /**
     * Hard delete for data-entry mistakes; refused when payments exist (FK is RESTRICT anyway).
     */
    public function delete(int $id): bool
    {
        if ($this->payments->where('appointment_id', $id)->countAllResults() > 0) {
            throw new InvalidArgumentException('Appointment has payments; cancel it instead of deleting.');
        }

        return $this->appointments->delete($id);
    }

    /**
     * Appointment with customer, items, payments summary and reminders.
     */
    public function withDetails(int $id): ?array
    {
        $appointment = $this->appointments->find($id);

        if ($appointment === null) {
            return null;
        }

        $customer = $this->customers->withDeleted()->find((int) $appointment['customer_id']);
        $paid     = $this->payments->netPaidForAppointment($id);

        return $appointment + [
            'customer'  => $customer,
            'items'     => $this->items->forAppointment($id),
            'payments'  => $this->payments->where('appointment_id', $id)->orderBy('paid_at', 'ASC')->findAll(),
            'paid'      => $paid,
            'balance'   => max(0, (int) $appointment['total'] - $paid),
            'reminders' => $this->reminders->where('appointment_id', $id)->orderBy('due_at', 'ASC')->findAll(),
        ];
    }

    /**
     * Attaches customer and items to a list of appointment rows (two extra queries, no N+1).
     *
     * @param list<array<string, mixed>> $rows
     */
    public function attachRelations(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids         = array_column($rows, 'id');
        $customerIds = array_values(array_unique(array_column($rows, 'customer_id')));
        $customers   = [];

        foreach ($this->customers->withDeleted()->whereIn('id', $customerIds)->findAll() as $c) {
            $customers[(int) $c['id']] = ['id' => (int) $c['id'], 'name' => $c['name'], 'phone' => $c['phone']];
        }

        $items = [];

        foreach ($this->items->whereIn('appointment_id', $ids)->orderBy('id', 'ASC')->findAll() as $item) {
            $items[(int) $item['appointment_id']][] = $item;
        }

        foreach ($rows as &$row) {
            $row['customer'] = $customers[(int) $row['customer_id']] ?? null;
            $row['items']    = $items[(int) $row['id']] ?? [];
        }

        return $rows;
    }

    /**
     * Pending "appointment" reminder due settings.reminder_lead_hours before the slot (if the customer opted in).
     */
    public function scheduleAppointmentReminder(int $appointmentId): ?int
    {
        $appointment = $this->appointments->find($appointmentId);
        $customer    = $appointment ? $this->customers->find((int) $appointment['customer_id']) : null;
        $leadHours   = (int) ($this->settings->value('reminder_lead_hours', '24') ?? 24);

        if ($appointment === null || $customer === null || (int) $customer['whatsapp_opt_in'] !== 1 || $leadHours <= 0) {
            return null;
        }

        if (! in_array($appointment['status'], AppointmentModel::BLOCKING_STATUSES, true) || $appointment['status'] === AppointmentModel::STATUS_COMPLETED) {
            return null;
        }

        $due = Time::parse($appointment['starts_at'])->subHours($leadHours);
        $now = Time::now();

        if ($due->isBefore($now)) {
            $due = $now;
        }

        $message = service('whatsapp')->appointmentMessage($customer, $appointment, $this->items->forAppointment($appointmentId));

        $id = $this->reminders->insert([
            'customer_id'    => (int) $customer['id'],
            'appointment_id' => $appointmentId,
            'type'           => 'appointment',
            'due_at'         => $due->toDateTimeString(),
            'channel'        => 'whatsapp',
            'message'        => $message,
            'status'         => 'pending',
        ]);

        return $id === false ? null : (int) $id;
    }

    /**
     * Follow-up reminder settings.followup_days after a completed appointment.
     */
    public function scheduleFollowUp(int $appointmentId): ?int
    {
        $appointment = $this->appointments->find($appointmentId);
        $customer    = $appointment ? $this->customers->find((int) $appointment['customer_id']) : null;
        $days        = (int) ($this->settings->value('followup_days', '30') ?? 30);

        if ($appointment === null || $customer === null || (int) $customer['whatsapp_opt_in'] !== 1 || $days <= 0) {
            return null;
        }

        $id = $this->reminders->insert([
            'customer_id'    => (int) $customer['id'],
            'appointment_id' => $appointmentId,
            'type'           => 'follow_up',
            'due_at'         => Time::parse($appointment['starts_at'])->addDays($days)->toDateTimeString(),
            'channel'        => 'whatsapp',
            'message'        => service('whatsapp')->followUpMessage($customer),
            'status'         => 'pending',
        ]);

        return $id === false ? null : (int) $id;
    }
}
