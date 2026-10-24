<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\ReminderModel;
use App\Models\SettingModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\I18n\Time;

/**
 * php spark reminders:generate [--dry-run]
 *
 * Daily job (cPanel cron) that creates the WhatsApp reminders the owner then sends by hand:
 *   - birthday reminders for customers whose birthday is today,
 *   - follow-up reminders for customers whose last completed appointment was settings.followup_days ago,
 *   - a safety net: appointment reminders for upcoming appointments that have none.
 * Idempotent: running it several times a day creates nothing twice.
 */
class RemindersGenerate extends BaseCommand
{
    protected $group       = 'Maintenance';
    protected $name        = 'reminders:generate';
    protected $description = 'Creates birthday, follow-up and missing appointment reminders (manual WhatsApp to-do list).';
    protected $usage       = 'reminders:generate [--dry-run]';
    protected $options     = ['--dry-run' => 'Report what would be created without writing anything.'];

    public function run(array $params)
    {
        $dry      = array_key_exists('dry-run', $params) || CLI::getOption('dry-run') !== null;
        $now      = Time::now();
        $today    = $now->toDateString();
        $settings = new SettingModel();
        $wa       = service('whatsapp');
        $model    = new ReminderModel();
        $created  = ['birthday' => 0, 'follow_up' => 0, 'appointment' => 0];

        // 1) birthdays today (any year), opted-in customers, one reminder per customer per day
        $monthDay = $now->format('m-d');

        foreach ((new CustomerModel())->where('whatsapp_opt_in', 1)->where('birthday IS NOT NULL')->findAll() as $customer) {
            if (substr((string) $customer['birthday'], 5, 5) !== $monthDay) {
                continue;
            }

            $exists = $model->where('customer_id', $customer['id'])->where('type', 'birthday')
                ->where('due_at >=', $today . ' 00:00:00')->where('due_at <=', $today . ' 23:59:59')->countAllResults();

            if ($exists > 0) {
                continue;
            }

            $due = $this->notBeforeNow($today . ' 09:00:00', $now);
            CLI::write("birthday   -> {$customer['name']} ({$customer['phone']}) due {$due}");
            $created['birthday']++;

            if (! $dry) {
                $model->insert(['customer_id' => $customer['id'], 'type' => 'birthday', 'due_at' => $due, 'channel' => 'whatsapp', 'message' => $wa->birthdayMessage($customer), 'status' => 'pending']);
            }
        }

        // 2) follow-ups: last completed appointment exactly followup_days ago, no newer follow-up, nothing upcoming
        $days = (int) ($settings->value('followup_days', '30') ?? 30);

        if ($days > 0) {
            $targetDay = $now->subDays($days)->toDateString();
            $completed = (new AppointmentModel())->where('status', AppointmentModel::STATUS_COMPLETED)
                ->where('starts_at >=', $targetDay . ' 00:00:00')->where('starts_at <=', $targetDay . ' 23:59:59')
                ->orderBy('starts_at', 'DESC')->findAll();
            $seen = [];

            foreach ($completed as $appointment) {
                $customerId = (int) $appointment['customer_id'];

                if (isset($seen[$customerId])) {
                    continue;
                }
                $seen[$customerId] = true;

                $customer = (new CustomerModel())->find($customerId);

                if ($customer === null || (int) $customer['whatsapp_opt_in'] !== 1) {
                    continue;
                }

                $later = (new AppointmentModel())->where('customer_id', $customerId)
                    ->where('starts_at >', $appointment['starts_at'])
                    ->whereIn('status', AppointmentModel::BLOCKING_STATUSES)->countAllResults();
                $existing = $model->where('customer_id', $customerId)->where('type', 'follow_up')
                    ->where('due_at >=', $appointment['starts_at'])->countAllResults();

                if ($later > 0 || $existing > 0) {
                    continue;
                }

                $due = $this->notBeforeNow($today . ' 10:00:00', $now);
                CLI::write("follow_up  -> {$customer['name']} ({$customer['phone']}) last visit {$appointment['starts_at']}, due {$due}");
                $created['follow_up']++;

                if (! $dry) {
                    $model->insert(['customer_id' => $customerId, 'appointment_id' => (int) $appointment['id'], 'type' => 'follow_up', 'due_at' => $due, 'channel' => 'whatsapp', 'message' => $wa->followUpMessage($customer), 'status' => 'pending']);
                }
            }
        }

        // 3) safety net: upcoming appointments (within lead hours + 1 day) without an appointment reminder
        $lead     = (int) ($settings->value('reminder_lead_hours', '24') ?? 24);
        $upcoming = (new AppointmentModel())
            ->whereIn('status', [AppointmentModel::STATUS_SCHEDULED, AppointmentModel::STATUS_CONFIRMED])
            ->where('starts_at >', $now->toDateTimeString())
            ->where('starts_at <=', $now->addHours(max(0, $lead) + 24)->toDateTimeString())
            ->findAll();

        foreach ($upcoming as $appointment) {
            $has = $model->where('appointment_id', $appointment['id'])->where('type', 'appointment')->countAllResults();

            if ($has > 0) {
                continue;
            }

            $customer = (new CustomerModel())->find((int) $appointment['customer_id']);

            if ($customer === null || (int) $customer['whatsapp_opt_in'] !== 1) {
                continue;
            }

            CLI::write("appointment-> {$customer['name']} ({$customer['phone']}) at {$appointment['starts_at']}");
            $created['appointment']++;

            if (! $dry) {
                service('appointments')->scheduleAppointmentReminder((int) $appointment['id']);
            }
        }

        CLI::write(sprintf(
            '[%s] %sbirthday %d, follow-up %d, appointment %d',
            $now->toDateTimeString(),
            $dry ? 'DRY RUN - would create: ' : 'created: ',
            $created['birthday'],
            $created['follow_up'],
            $created['appointment'],
        ));

        return EXIT_SUCCESS;
    }

    private function notBeforeNow(string $dateTime, Time $now): string
    {
        return $dateTime < $now->toDateTimeString() ? $now->toDateTimeString() : $dateTime;
    }
}
