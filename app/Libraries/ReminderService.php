<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\ReminderModel;
use InvalidArgumentException;
use RuntimeException;

/**
 * The owner's manual WhatsApp to-do list, shared by the JSON API and the web UI.
 * Every decorated row carries `wa_link`: tapping it opens the owner's own WhatsApp with the
 * message prefilled; the owner sends it and then marks the reminder as sent. Nothing is sent
 * from the server. Registered as the shared service `reminders`.
 */
class ReminderService
{
    public function __construct(
        private readonly ReminderModel $reminders = new ReminderModel(),
        private readonly CustomerModel $customers = new CustomerModel(),
        private readonly AppointmentModel $appointments = new AppointmentModel(),
    ) {
    }

    /**
     * Adds customer, appointment summary and the click-to-chat link to reminder rows.
     *
     * @param list<array<string, mixed>> $rows
     */
    public function decorate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $wa        = service('whatsapp');
        $customers = [];
        $appts     = [];

        foreach ($this->customers->withDeleted()->whereIn('id', array_values(array_unique(array_column($rows, 'customer_id'))))->findAll() as $c) {
            $customers[(int) $c['id']] = ['id' => (int) $c['id'], 'name' => $c['name'], 'phone' => $c['phone'], 'whatsapp_opt_in' => (int) $c['whatsapp_opt_in']];
        }

        $apptIds = array_values(array_filter(array_unique(array_column($rows, 'appointment_id'))));

        if ($apptIds !== []) {
            foreach ($this->appointments->whereIn('id', $apptIds)->findAll() as $a) {
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

    /**
     * Creates a `custom` reminder written by the owner.
     *
     * @param array{customer_id: int|string, message: string, due_at: string, appointment_id?: int|string|null} $data
     *
     * @throws InvalidArgumentException when the customer does not exist
     * @throws RuntimeException         when the model rejects the row
     */
    public function createCustom(array $data): int
    {
        if ($this->customers->find((int) $data['customer_id']) === null) {
            throw new InvalidArgumentException('Customer not found.');
        }

        $id = $this->reminders->insert([
            'customer_id'    => (int) $data['customer_id'],
            'appointment_id' => ! empty($data['appointment_id']) ? (int) $data['appointment_id'] : null,
            'type'           => 'custom',
            'due_at'         => $data['due_at'],
            'channel'        => 'whatsapp',
            'message'        => trim((string) $data['message']),
            'status'         => 'pending',
        ]);

        if ($id === false) {
            throw new RuntimeException(implode(' ', $this->reminders->errors()));
        }

        return (int) $id;
    }

    /**
     * pending | sent | skipped | cancelled. Returns false when the reminder does not exist.
     */
    public function setStatus(int $id, string $status): bool
    {
        if (! in_array($status, ReminderModel::STATUSES, true)) {
            throw new InvalidArgumentException('Unknown status.');
        }

        if ($this->reminders->find($id) === null) {
            return false;
        }

        if ($status === 'sent') {
            return $this->reminders->markSent($id);
        }

        return $this->reminders->update($id, ['status' => $status, 'sent_at' => null]);
    }
}
