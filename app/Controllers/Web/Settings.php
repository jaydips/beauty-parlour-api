<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Database\Seeds\SettingsSeeder;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Parlour settings form (same keys and validation as PUT /settings), including the
 * "auto WhatsApp on new booking" switch (wa_auto_send) with the bridge link state and the send window.
 *   GET /app/settings   POST /app/settings
 */
class Settings extends WebController
{
    /** Keys the form may change, in order. Labels come from lang('App.setting_<key>'). */
    public const KEYS = [
        'wa_auto_send',
        'wa_send_from',
        'wa_send_to',
        'parlour_name',
        'parlour_phone',
        'parlour_address',
        'country_code',
        'slot_minutes',
        'reminder_lead_hours',
        'followup_days',
        'tpl_whatsapp_booking',
        'tpl_whatsapp_reminder',
        'tpl_whatsapp_followup',
        'tpl_whatsapp_birthday',
        'tpl_whatsapp_offer',
        'tpl_whatsapp_review',
        'google_review_url',
        'instagram_url',
    ];

    /**
     * @return array<string, string> key => Gujarati label
     */
    public static function fields(): array
    {
        $out = [];

        foreach (self::KEYS as $key) {
            $out[$key] = lang('App.setting_' . $key);
        }

        return $out;
    }

    public function show(): string
    {
        $values = (new SettingModel())->allValues();

        foreach (SettingsSeeder::DEFAULTS as $key => $default) {
            $values[$key] ??= $default;
        }

        return $this->render('web/settings', [
            'title'       => lang('App.title_settings'),
            'values'      => $values,
            'fields'      => self::fields(),
            'bridgeState' => WhatsApp::bridgeState(),
        ], 'more');
    }

    public function update(): RedirectResponse
    {
        $data   = $this->input();
        $fields = self::fields();
        $errors = [];
        $clean  = [];

        foreach (self::KEYS as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = trim((string) $data[$key]);

            if ($key === 'parlour_phone' || $key === 'country_code') {
                $value = preg_replace('/\D+/', '', $value);
            }

            $error = SettingModel::validateValue($key, $value);

            if ($error !== null) {
                $errors[] = $fields[$key] . ': ' . $error;
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

        $message = lang('App.settings_saved');

        if (($clean['wa_auto_send'] ?? null) === '1') {
            $message .= ' ' . lang('App.auto_send_on');
        }

        return redirect()->to(site_url('app/settings'))->with('success', $message);
    }
}
