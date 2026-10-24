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
            'country_code'                                         => preg_match('/^\d{1,3}$/', $value) ? null : 'Country code must be 1 to 3 digits.',
            'parlour_phone'                                        => ($value === '' || preg_match('/^\d{10}$/', $value)) ? null : 'Phone must be 10 digits.',
            'slot_minutes', 'reminder_lead_hours', 'followup_days' => ctype_digit($value) ? null : 'Must be a whole number (0 or more).',
            'opening_hours'                                        => json_decode($value, true) !== null || $value === 'null' ? null : 'Must be valid JSON.',
            'timezone'                                             => in_array($value, timezone_identifiers_list(), true) ? null : 'Unknown timezone.',
            'wa_auto_send'                                         => in_array($value, ['0', '1'], true) ? null : 'Must be 0 or 1.',
            'wa_send_from', 'wa_send_to'                           => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? null : 'Time must be HH:MM (24h).',
            default                                                => mb_strlen($value) <= 2000 ? null : 'Too long (max 2000 characters).',
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
