<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\AppointmentModel;
use App\Models\AppointmentServiceModel;
use App\Models\CampaignModel;
use App\Models\CustomerModel;
use App\Models\MessageModel;
use App\Models\ReminderModel;
use App\Models\SettingModel;
use CodeIgniter\I18n\Time;
use Config\WhatsApp as WhatsAppConfig;
use RuntimeException;

/**
 * Automatic WhatsApp sending on top of the manual reminders:
 *   - enqueue*()      put messages in the outbox (booking confirmations, due reminders, offers)
 *   - process()       deliver queued messages through the bridge with pacing, retries and a send window
 * Everything is switched off while settings.wa_auto_send is "0"; the owner's wa.me links keep working.
 */
class Outbox
{
    /** Bridge answers that mean "try again later, nothing is wrong with this message". */
    private const DEFER_ERRORS = ['not_linked', 'not_connected', 'bridge_unreachable', 'bridge_not_configured', 'rate_limited', 'bad_response', 'unauthorized'];

    /** Bridge answers that mean this message can never be delivered. */
    private const FATAL_ERRORS = ['not_on_whatsapp', 'invalid_request', 'invalid_phone'];

    private readonly WhatsAppConfig $config;

    public function __construct(
        private readonly MessageModel $messages = new MessageModel(),
        private readonly ReminderModel $reminders = new ReminderModel(),
        private readonly CustomerModel $customers = new CustomerModel(),
        private readonly CampaignModel $campaigns = new CampaignModel(),
        private readonly SettingModel $settings = new SettingModel(),
        ?WhatsAppConfig $config = null,
    ) {
        $this->config = $config ?? config('WhatsApp');
    }

    // ---- switches ---------------------------------------------------------------------

    public function autoSendEnabled(): bool
    {
        return $this->settings->value('wa_auto_send', '0') === '1';
    }

    /**
     * @return array{0: string, 1: string} [from, to] as "H:i"; equal values mean "any time"
     */
    public function sendWindow(): array
    {
        $from = (string) $this->settings->value('wa_send_from', '09:00');
        $to   = (string) $this->settings->value('wa_send_to', '21:00');

        return [preg_match('/^\d{2}:\d{2}$/', $from) ? $from : '09:00', preg_match('/^\d{2}:\d{2}$/', $to) ? $to : '21:00'];
    }

    public function inSendWindow(Time $now): bool
    {
        [$from, $to] = $this->sendWindow();

        if ($from === $to) {
            return true;
        }

        $hm = $now->format('H:i');

        return $from < $to ? ($hm >= $from && $hm < $to) : ($hm >= $from || $hm < $to);
    }

    // ---- enqueue ----------------------------------------------------------------------

    /**
     * Adds one message for a customer. Returns null for customers without a usable 10-digit phone.
     *
     * @param array<string, int|null> $refs appointment_id / reminder_id / campaign_id
     */
    public function enqueue(array $customer, string $type, string $body, array $refs = [], ?string $scheduledAt = null): ?int
    {
        $phone = preg_replace('/\D+/', '', (string) ($customer['phone'] ?? '')) ?? '';
        $body  = trim($body);

        if (strlen($phone) !== 10 || $body === '') {
            return null;
        }

        $id = $this->messages->insert([
            'customer_id'    => isset($customer['id']) ? (int) $customer['id'] : null,
            'appointment_id' => $refs['appointment_id'] ?? null,
            'reminder_id'    => $refs['reminder_id'] ?? null,
            'campaign_id'    => $refs['campaign_id'] ?? null,
            'phone'          => $phone,
            'type'           => in_array($type, MessageModel::TYPES, true) ? $type : 'custom',
            'body'           => mb_substr($body, 0, 2000),
            'status'         => 'queued',
            'attempts'       => 0,
            'scheduled_at'   => $scheduledAt ?? Time::now()->toDateTimeString(),
        ]);

        return $id === false ? null : (int) $id;
    }

    /**
     * "Your appointment is booked" message right after a booking (idempotent per appointment).
     */
    public function enqueueBookingConfirmation(int $appointmentId): ?int
    {
        if (! $this->autoSendEnabled()) {
            return null;
        }

        $appointment = (new AppointmentModel())->find($appointmentId);
        $customer    = $appointment ? $this->customers->find((int) $appointment['customer_id']) : null;

        if ($appointment === null || $customer === null || (int) $customer['whatsapp_opt_in'] !== 1) {
            return null;
        }

        if ($this->messages->where('appointment_id', $appointmentId)->where('type', 'booking')->countAllResults() > 0) {
            return null;
        }

        $items = (new AppointmentServiceModel())->forAppointment($appointmentId);
        $body  = service('whatsapp')->bookingMessage($customer, $appointment, $items);

        return $this->enqueue($customer, 'booking', $body, ['appointment_id' => $appointmentId]);
    }

    /**
     * Cancels the queued (not yet sent) outbox rows of an appointment: all of them, or only the
     * given types. Used when an appointment is cancelled, completed or moved. Returns how many.
     *
     * @param list<string>|null $types e.g. ['booking', 'appointment']
     */
    public function cancelForAppointment(int $appointmentId, ?array $types = null): int
    {
        $query = $this->messages->where('appointment_id', $appointmentId)->where('status', 'queued');

        if ($types !== null) {
            $query->whereIn('type', $types);
        }

        $ids = array_map('intval', array_column($query->findAll(), 'id'));

        if ($ids === []) {
            return 0;
        }

        $this->messages->builder()->whereIn('id', $ids)
            ->update(['status' => 'cancelled', 'updated_at' => Time::now()->toDateTimeString()]);

        return count($ids);
    }

    /**
     * After the time or the services of an appointment changed: a booking confirmation that is still
     * queued gets the new text (the customer must not receive the old date); one that was already sent
     * is left alone. Nothing is queued here - that is enqueueBookingConfirmation()'s job at booking time.
     */
    public function refreshBookingConfirmation(int $appointmentId): void
    {
        $row = $this->messages->where('appointment_id', $appointmentId)->where('type', 'booking')->where('status', 'queued')->first();

        if ($row === null) {
            return;
        }

        $appointment = (new AppointmentModel())->find($appointmentId);
        $customer    = $appointment ? $this->customers->find((int) $appointment['customer_id']) : null;

        if ($appointment === null || $customer === null) {
            $this->messages->cancel((int) $row['id']);

            return;
        }

        $items = (new AppointmentServiceModel())->forAppointment($appointmentId);
        $body  = service('whatsapp')->bookingMessage($customer, $appointment, $items);

        $this->messages->update((int) $row['id'], ['body' => mb_substr($body, 0, 2000)]);
    }

    /**
     * Turns every due, pending reminder into an outbox row (once). Returns how many were queued.
     */
    public function enqueueDueReminders(Time $now): int
    {
        if (! $this->autoSendEnabled()) {
            return 0;
        }

        $count = 0;

        foreach ($this->reminders->due($now->toDateTimeString()) as $reminder) {
            if (($reminder['channel'] ?? 'whatsapp') !== 'whatsapp') {
                continue;
            }

            if ($this->messages->where('reminder_id', (int) $reminder['id'])->countAllResults() > 0) {
                continue;
            }

            $customer = $this->customers->find((int) $reminder['customer_id']);

            if ($customer === null || (int) $customer['whatsapp_opt_in'] !== 1) {
                continue;
            }

            $id = $this->enqueue($customer, (string) $reminder['type'], (string) $reminder['message'], [
                'reminder_id'    => (int) $reminder['id'],
                'appointment_id' => $reminder['appointment_id'] !== null ? (int) $reminder['appointment_id'] : null,
            ]);

            if ($id !== null) {
                $count++;
            }
        }

        return $count;
    }

    // ---- campaigns (offers) -----------------------------------------------------------

    /**
     * Opted-in customers with a valid phone for an audience: all, recent (completed visit within
     * $days) or inactive (no completed visit within $days).
     *
     * @return list<array<string, mixed>>
     */
    public function recipients(string $audience, ?int $days = null): array
    {
        $customers = $this->customers->where('whatsapp_opt_in', 1)->orderBy('name', 'ASC')->findAll();
        $customers = array_values(array_filter($customers, static fn (array $c): bool => preg_match('/^\d{10}$/', (string) $c['phone']) === 1));

        if ($audience === 'all' || $days === null || $days <= 0) {
            return $customers;
        }

        $since  = Time::now()->subDays($days)->toDateTimeString();
        $recent = array_map('intval', array_column(
            (new AppointmentModel())->select('customer_id')->distinct()
                ->where('status', AppointmentModel::STATUS_COMPLETED)->where('starts_at >=', $since)->findAll(),
            'customer_id',
        ));

        return array_values(array_filter(
            $customers,
            static fn (array $c): bool => $audience === 'recent' ? in_array((int) $c['id'], $recent, true) : ! in_array((int) $c['id'], $recent, true),
        ));
    }

    /**
     * Creates a campaign and one spread-out outbox row per recipient. The body may use
     * {customer_name} and {parlour_name}.
     *
     * @return array{id: int, recipients: int}
     */
    public function createCampaign(string $title, string $body, string $audience, ?int $days, ?Time $now = null): array
    {
        $now ??= Time::now();
        $recipients = $this->recipients($audience, $days);
        $campaignId = $this->campaigns->insert([
            'title'         => $title,
            'body'          => $body,
            'audience'      => $audience,
            'audience_days' => $audience === 'all' ? null : $days,
            'recipients'    => count($recipients),
            'sent'          => 0,
            'failed'        => 0,
            'status'        => $recipients === [] ? 'done' : 'queued',
        ]);

        if ($campaignId === false) {
            throw new RuntimeException(implode(' ', $this->campaigns->errors()));
        }

        $wa     = service('whatsapp');
        $gap    = max(0, $this->config->campaignGapSeconds);
        $queued = 0;

        foreach ($recipients as $i => $customer) {
            $text = $wa->render($body, $wa->baseVars($customer));
            $at   = $now->addSeconds($i * $gap)->toDateTimeString();

            if ($this->enqueue($customer, 'offer', $text, ['campaign_id' => (int) $campaignId], $at) !== null) {
                $queued++;
            }
        }

        if ($queued !== count($recipients)) {
            $this->campaigns->update((int) $campaignId, ['recipients' => $queued, 'status' => $queued === 0 ? 'done' : 'queued']);
        }

        return ['id' => (int) $campaignId, 'recipients' => $queued];
    }

    /**
     * Cancels the queued messages of a campaign. Returns how many were cancelled.
     */
    public function cancelCampaign(int $campaignId): int
    {
        if ($this->campaigns->find($campaignId) === null) {
            return 0;
        }

        $n = $this->messages->where('campaign_id', $campaignId)->where('status', 'queued')->countAllResults();

        if ($n > 0) {
            $this->messages->builder()->where('campaign_id', $campaignId)->where('status', 'queued')
                ->update(['status' => 'cancelled', 'updated_at' => Time::now()->toDateTimeString()]);
        }

        $this->campaigns->update($campaignId, ['status' => 'cancelled']);

        return $n;
    }

    /**
     * Recomputes sent/failed counters and closes the campaign when nothing is queued any more.
     */
    public function refreshCampaign(int $campaignId): void
    {
        $campaign = $this->campaigns->find($campaignId);

        if ($campaign === null) {
            return;
        }

        $counts = $this->messages->counts(['campaign_id' => $campaignId]);
        $data   = ['sent' => $counts['sent'], 'failed' => $counts['failed']];

        if ($campaign['status'] !== 'cancelled' && $counts['queued'] === 0) {
            $data['status'] = 'done';
        }

        $this->campaigns->update($campaignId, $data);
    }

    // ---- delivery ---------------------------------------------------------------------

    /**
     * One cron run: queue due reminders, then send what is due (inside the send window) through the bridge.
     *
     * @return array{queued_reminders: int, sent: int, failed: int, deferred: int, skipped: ?string}
     */
    public function process(?Time $now = null, ?int $limit = null): array
    {
        $now ??= Time::now();
        $summary = ['queued_reminders' => 0, 'sent' => 0, 'failed' => 0, 'deferred' => 0, 'skipped' => null];

        if (! $this->autoSendEnabled()) {
            $summary['skipped'] = 'auto_send_off';

            return $summary;
        }

        $summary['queued_reminders'] = $this->enqueueDueReminders($now);

        if (! $this->inSendWindow($now)) {
            $summary['skipped'] = 'outside_send_window';

            return $summary;
        }

        $batch = $this->messages->dueBatch($now->toDateTimeString(), $limit ?? $this->config->perRun);

        if ($batch === []) {
            return $summary;
        }

        $bridge = service('whatsappBridge');
        $status = $bridge->status();

        if (! ($status['ok'] ?? false) || ! ($status['registered'] ?? false)) {
            $summary['skipped']  = 'bridge_' . preg_replace('/^bridge_/', '', (string) ($status['error'] ?? 'not_linked'));
            $summary['deferred'] = count($batch);

            return $summary;
        }

        foreach ($batch as $i => $message) {
            if ($i > 0 && $this->config->gapSeconds > 0) {
                sleep($this->config->gapSeconds);
            }

            $result   = $bridge->send((string) $message['phone'], (string) $message['body']);
            $attempts = (int) $message['attempts'] + 1;

            if ($result['ok'] ?? false) {
                $this->messages->update((int) $message['id'], [
                    'status'     => 'sent',
                    'attempts'   => $attempts,
                    'sent_at'    => Time::now()->toDateTimeString(),
                    'wa_id'      => isset($result['id']) ? mb_substr((string) $result['id'], 0, 64) : null,
                    'last_error' => null,
                ]);
                $this->afterSent($message);
                $summary['sent']++;

                continue;
            }

            $error = (string) ($result['error'] ?? 'unknown_error');

            if (in_array($error, self::DEFER_ERRORS, true)) {
                // The bridge itself is the problem: keep the whole batch for the next run.
                $this->messages->update((int) $message['id'], ['last_error' => $error]);
                $summary['deferred'] += count($batch) - $i;
                $summary['skipped'] = 'bridge_' . $error;

                break;
            }

            $fatal = in_array($error, self::FATAL_ERRORS, true) || $attempts >= $this->config->maxAttempts;

            $this->messages->update((int) $message['id'], [
                'status'       => $fatal ? 'failed' : 'queued',
                'attempts'     => $attempts,
                'last_error'   => mb_substr($error, 0, 255),
                'scheduled_at' => $fatal ? $message['scheduled_at'] : $now->addMinutes(10)->toDateTimeString(),
            ]);

            if ($fatal) {
                $this->afterFailed($message);
                $summary['failed']++;
            } else {
                $summary['deferred']++;
            }
        }

        return $summary;
    }

    /**
     * Adds a compact customer to each message row (name, phone, opt-in) for lists.
     *
     * @param list<array<string, mixed>> $rows
     */
    public function decorate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids       = array_values(array_filter(array_unique(array_column($rows, 'customer_id'))));
        $customers = [];

        if ($ids !== []) {
            foreach ($this->customers->withDeleted()->whereIn('id', $ids)->findAll() as $c) {
                $customers[(int) $c['id']] = ['id' => (int) $c['id'], 'name' => $c['name'], 'phone' => $c['phone']];
            }
        }

        foreach ($rows as &$row) {
            $row['customer'] = $row['customer_id'] ? ($customers[(int) $row['customer_id']] ?? null) : null;
        }

        return $rows;
    }

    private function afterSent(array $message): void
    {
        if (! empty($message['reminder_id'])) {
            $this->reminders->markSent((int) $message['reminder_id']);
        }

        if (! empty($message['campaign_id'])) {
            $this->refreshCampaign((int) $message['campaign_id']);
        }
    }

    private function afterFailed(array $message): void
    {
        if (! empty($message['campaign_id'])) {
            $this->refreshCampaign((int) $message['campaign_id']);
        }
    }
}
