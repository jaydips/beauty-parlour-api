<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

/**
 * Key/value parlour configuration. Kept as key/value so new settings never
 * need a schema change (the hosting SQLite cannot alter columns).
 */
class SettingModel extends Model
{
    protected $table            = 'settings';
    protected $primaryKey       = 'key';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $allowedFields    = ['key', 'value'];
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = '';
    protected $updatedField     = 'updated_at';

    protected $validationRules = [
        'key'   => 'required|alpha_dash|max_length[64]',
        'value' => 'permit_empty|string',
    ];

    /**
     * Returns one setting value, or $default when the key does not exist.
     */
    public function value(string $key, ?string $default = null): ?string
    {
        $row = $this->find($key);

        return $row === null ? $default : $row['value'];
    }

    /**
     * Inserts or updates a setting without using upsert()
     * (not available on the hosting SQLite version).
     */
    public function setValue(string $key, ?string $value): bool
    {
        $now = Time::now()->toDateTimeString();

        if ($this->find($key) === null) {
            return $this->insert(['key' => $key, 'value' => $value, 'updated_at' => $now], false) !== false;
        }

        return $this->update($key, ['value' => $value, 'updated_at' => $now]);
    }

    /**
     * Validation shared by the JSON API and the web settings form.
     * Returns an error message for a bad value, or null when it is acceptable.
     */
    public static function validateValue(string $key, string $value): ?string
    {
        return match ($key) {
            'country_code'                                         => preg_match('/^\d{1,3}$/', $value) ? null : lang('App.v_country_code'),
            'parlour_phone'                                        => ($value === '' || preg_match('/^\d{10}$/', $value)) ? null : lang('App.v_phone_10'),
            'slot_minutes', 'reminder_lead_hours', 'followup_days', 'wa_daily_cap', 'wa_campaign_warmup_hours' => ctype_digit($value) ? null : lang('App.v_whole_number'),
            'opening_hours'                                        => json_decode($value, true) !== null || $value === 'null' ? null : lang('App.v_json'),
            'timezone'                                             => in_array($value, timezone_identifiers_list(), true) ? null : lang('App.v_timezone'),
            'wa_auto_send'                                         => in_array($value, ['0', '1'], true) ? null : lang('App.v_zero_one'),
            'wa_send_from', 'wa_send_to'                           => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? null : lang('App.v_time_hhmm'),
            'google_review_url', 'instagram_url', 'whatsapp_group_url' => ($value === '' || (preg_match('#^https?://\S+$#i', $value) && mb_strlen($value) <= 500)) ? null : lang('App.v_url'),
            default                                                => mb_strlen($value) <= 2000 ? null : lang('App.v_too_long'),
        };
    }

    /**
     * Returns all settings as an associative array key => value.
     *
     * @return array<string, string|null>
     */
    public function allValues(): array
    {
        $out = [];

        foreach ($this->findAll() as $row) {
            $out[$row['key']] = $row['value'];
        }

        return $out;
    }
}
