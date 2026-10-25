<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Database\Seeds\SettingsSeeder;
use App\Models\SettingModel;
use CodeIgniter\I18n\Time;

/**
 * Message templates and click-to-chat links.
 *
 * Renders the owner's WhatsApp templates (settings tpl_whatsapp_*, Gujarati by default) and builds
 * https://wa.me links for the manual flow. Nothing here sends a message: the manual flow opens the
 * link in the owner's own WhatsApp, the automatic flow (Outbox) stores the rendered text in the
 * outbox for `spark whatsapp:send`. Placeholders: {customer_name} {parlour_name} {parlour_address}
 * {parlour_phone} {date} {time} {services}; {date} is dd/mm/yyyy, {time} Gujarati ("સવારે 10:30").
 */
class WhatsApp
{
    public function __construct(private readonly SettingModel $settings = new SettingModel())
    {
    }

    /**
     * wa.me link for a 10-digit local number, using settings.country_code (default 91).
     * rawurlencode() percent-encodes the UTF-8 bytes, so Gujarati text survives the round trip.
     */
    public function link(string $phone, string $message): string
    {
        $cc    = preg_replace('/\D+/', '', (string) $this->settings->value('country_code', '91')) ?: '91';
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return 'https://wa.me/' . $cc . $digits . '?text=' . rawurlencode($message);
    }

    /**
     * Replaces {placeholders} in a template. A line that only carried an empty placeholder (for
     * example "📍 {parlour_address}" while no address is set) is dropped, so the signature block
     * never shows an orphaned emoji; lines with real text are always kept.
     *
     * @param array<string, scalar|null> $vars
     */
    public function render(string $template, array $vars): string
    {
        $search  = [];
        $replace = [];
        $empty   = [];

        foreach ($vars as $key => $value) {
            $value     = (string) ($value ?? '');
            $search[]  = '{' . $key . '}';
            $replace[] = $value;

            if (trim($value) === '') {
                $empty[] = '{' . $key . '}';
            }
        }

        $lines = [];

        foreach (preg_split('/\R/u', $template) ?: [] as $line) {
            $rendered = str_replace($search, $replace, $line);

            if ($empty !== [] && $this->containsAny($line, $empty) && ! preg_match('/[\p{L}\p{N}]/u', $rendered)) {
                continue; // nothing but decoration left after an empty placeholder
            }

            $lines[] = rtrim($rendered);
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Template text for a message type from settings (seeded defaults as fallback).
     */
    public function template(string $type): string
    {
        $key = match ($type) {
            'appointment' => 'tpl_whatsapp_reminder',
            'follow_up'   => 'tpl_whatsapp_followup',
            'birthday'    => 'tpl_whatsapp_birthday',
            'booking'     => 'tpl_whatsapp_booking',
            'offer'       => 'tpl_whatsapp_offer',
            default       => null,
        };

        if ($key === null) {
            return '{message}';
        }

        $value = $this->settings->value($key);

        return $value !== null && trim($value) !== '' ? $value : SettingsSeeder::DEFAULTS[$key];
    }

    /**
     * Common placeholder values.
     *
     * @return array<string, string>
     */
    public function baseVars(array $customer): array
    {
        return [
            'customer_name'   => (string) ($customer['name'] ?? ''),
            'parlour_name'    => (string) ($this->settings->value('parlour_name') ?: SettingsSeeder::DEFAULTS['parlour_name']),
            'parlour_address' => trim((string) $this->settings->value('parlour_address', '')),
            'parlour_phone'   => trim((string) $this->settings->value('parlour_phone', '')),
        ];
    }

    /**
     * {date} (dd/mm/yyyy), {time} (Gujarati, "સવારે 10:30") and {services} for an appointment.
     *
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, string>
     */
    public function appointmentVars(array $appointment, array $items): array
    {
        helper('format');

        $starts   = Time::parse((string) $appointment['starts_at']);
        $services = implode(', ', array_map(static fn (array $i): string => (string) $i['service_name'], $items));

        return [
            'date'     => $starts->format('d/m/Y'),
            'time'     => gu_time($starts),
            'services' => $services !== '' ? $services : lang('App.services_fallback'),
        ];
    }

    /**
     * Appointment reminder text with date, time and the booked services.
     *
     * @param list<array<string, mixed>> $items
     */
    public function appointmentMessage(array $customer, array $appointment, array $items): string
    {
        return $this->render($this->template('appointment'), $this->baseVars($customer) + $this->appointmentVars($appointment, $items));
    }

    /**
     * "Your appointment is booked" confirmation queued right after a booking (automatic sending only).
     *
     * @param list<array<string, mixed>> $items
     */
    public function bookingMessage(array $customer, array $appointment, array $items): string
    {
        return $this->render($this->template('booking'), $this->baseVars($customer) + $this->appointmentVars($appointment, $items));
    }

    public function followUpMessage(array $customer): string
    {
        return $this->render($this->template('follow_up'), $this->baseVars($customer));
    }

    public function birthdayMessage(array $customer): string
    {
        return $this->render($this->template('birthday'), $this->baseVars($customer));
    }

    /**
     * One-line text for the "test message" button and `spark whatsapp:test`.
     */
    public function testMessage(): string
    {
        return lang('App.test_message_text', [$this->baseVars([])['parlour_name']]);
    }
}
