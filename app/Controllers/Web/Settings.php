<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Database\Seeds\SettingsSeeder;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Parlour settings form (same keys and validation as PUT /settings).
 *   GET /app/settings   POST /app/settings
 */
class Settings extends WebController
{
    /** Keys shown on the form, in order, with Gujarati labels. */
    public const FIELDS = [
        'parlour_name'          => 'Parlour નું નામ',
        'parlour_phone'         => 'Parlour નો mobile (10 અંક)',
        'parlour_address'       => 'સરનામું',
        'country_code'          => 'Country code (WhatsApp માટે)',
        'slot_minutes'          => 'Slot (મિનિટ)',
        'reminder_lead_hours'   => 'Appointment reminder કેટલા કલાક પહેલાં',
        'followup_days'         => 'Follow-up કેટલા દિવસ પછી',
        'tpl_whatsapp_reminder' => 'WhatsApp template: appointment reminder',
        'tpl_whatsapp_followup' => 'WhatsApp template: follow-up',
        'tpl_whatsapp_birthday' => 'WhatsApp template: જન્મદિવસ',
        'tpl_whatsapp_booking'  => 'WhatsApp template: booking confirmation (auto-send)',
        'wa_send_from'          => 'Auto-send: આ સમયથી',
        'wa_send_to'            => 'Auto-send: આ સમય સુધી',
    ];

    public function show(): string
    {
        $values = (new SettingModel())->allValues();

        foreach (SettingsSeeder::DEFAULTS as $key => $default) {
            $values[$key] ??= $default;
        }

        return $this->render('web/settings', ['title' => 'Settings', 'values' => $values, 'fields' => self::FIELDS], 'more');
    }

    public function update(): RedirectResponse
    {
        $data   = $this->input();
        $errors = [];
        $clean  = [];

        foreach (array_keys(self::FIELDS) as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = trim((string) $data[$key]);

            if ($key === 'parlour_phone' || $key === 'country_code') {
                $value = preg_replace('/\D+/', '', $value);
            }

            $error = SettingModel::validateValue($key, $value);

            if ($error !== null) {
                $errors[] = self::FIELDS[$key] . ': ' . $error;
            } else {
                $clean[$key] = $value;
            }
        }

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('error', implode(' ', $errors));
        }

        $model = new SettingModel();

        foreach ($clean as $key => $value) {
            $model->setValue($key, $value);
        }

        return redirect()->to(site_url('app/settings'))->with('success', 'Settings સાચવ્યાં.');
    }
}
