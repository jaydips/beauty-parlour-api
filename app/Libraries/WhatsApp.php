<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Database\Seeds\SettingsSeeder;
use App\Models\SettingModel;
use CodeIgniter\I18n\Time;

/**
 * Manual click-to-chat only: builds https://wa.me links and renders message templates.
 * Nothing here sends a message; the owner taps the link in the app and sends from their own WhatsApp.
 */
class WhatsApp
{
    public function __construct(private readonly SettingModel $settings = new SettingModel())
    {
    }

    /**
     * wa.me link for a 10-digit local number, using settings.country_code (default 91).
     */
    public function link(string $phone, string $message): string
    {
        $cc    = preg_replace('/\D+/', '', (string) $this->settings->value('country_code', '91')) ?: '91';
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return 'https://wa.me/' . $cc . $digits . '?text=' . rawurlencode($message);
    }

    /**
     * Replaces {placeholders} in a template.
     *
     * @param array<string, scalar|null> $vars
     */
    public function render(string $template, array $vars): string
    {
        $search  = [];
        $replace = [];

        foreach ($vars as $key => $value) {
            $search[]  = '{' . $key . '}';
            $replace[] = (string) ($value ?? '');
        }

        return trim(str_replace($search, $replace, $template));
    }

    /**
     * Template text for a reminder type from settings (seeded defaults as fallback).
     */
    public function template(string $type): string
    {
        $key = match ($type) {
            'appointment' => 'tpl_whatsapp_reminder',
            'follow_up'   => 'tpl_whatsapp_followup',
            'birthday'    => 'tpl_whatsapp_birthday',
            default       => null,
        };

        if ($key === null) {
            return '{message}';
        }

        return (string) ($this->settings->value($key) ?? SettingsSeeder::DEFAULTS[$key]);
    }

    /**
     * Common placeholder values.
     *
     * @return array<string, string>
     */
    public function baseVars(array $customer): array
    {
        return [
            'customer_name' => (string) ($customer['name'] ?? ''),
            'parlour_name'  => (string) ($this->settings->value('parlour_name') ?? SettingsSeeder::DEFAULTS['parlour_name']),
        ];
    }

    /**
     * Appointment reminder text with date, time and the booked services.
     *
     * @param list<array<string, mixed>> $items
     */
    public function appointmentMessage(array $customer, array $appointment, array $items): string
    {
        $starts   = Time::parse($appointment['starts_at']);
        $services = implode(', ', array_map(static fn (array $i): string => (string) $i['service_name'], $items));

        return $this->render($this->template('appointment'), $this->baseVars($customer) + [
            'date'     => $starts->format('d M Y'),
            'time'     => $starts->format('h:i A'),
            'services' => $services !== '' ? $services : 'your appointment',
        ]);
    }

    public function followUpMessage(array $customer): string
    {
        return $this->render($this->template('follow_up'), $this->baseVars($customer));
    }

    public function birthdayMessage(array $customer): string
    {
        return $this->render($this->template('birthday'), $this->baseVars($customer));
    }
}
