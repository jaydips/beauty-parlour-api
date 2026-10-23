<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Database\Seeds\SettingsSeeder;
use App\Models\SettingModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * GET /settings            -> all settings as {key: value}
 * PUT /settings {key: value, ...} -> updates the given keys (only known keys are accepted)
 */
class Settings extends ApiController
{
    public function index(): ResponseInterface
    {
        return $this->respond($this->payload());
    }

    public function update(): ResponseInterface
    {
        $data    = $this->input();
        $allowed = array_keys(SettingsSeeder::DEFAULTS);
        $errors  = [];

        foreach ($data as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                $errors[$key] = 'Unknown setting.';

                continue;
            }

            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $value = $value === null ? '' : trim((string) $value);
            $error = $this->validateValue($key, $value);

            if ($error !== null) {
                $errors[$key] = $error;
            }
        }

        if ($errors !== []) {
            return $this->failValidationErrors($errors);
        }

        $model = new SettingModel();

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $model->setValue((string) $key, $value === null ? '' : trim((string) $value));
        }

        return $this->respond($this->payload());
    }

    private function validateValue(string $key, string $value): ?string
    {
        return match ($key) {
            'country_code'                                       => preg_match('/^\d{1,3}$/', $value) ? null : 'Country code must be 1 to 3 digits.',
            'parlour_phone'                                      => ($value === '' || preg_match('/^\d{10}$/', $value)) ? null : 'Phone must be 10 digits.',
            'slot_minutes', 'reminder_lead_hours', 'followup_days' => ctype_digit($value) ? null : 'Must be a whole number (0 or more).',
            'opening_hours'                                      => json_decode($value, true) !== null || $value === 'null' ? null : 'Must be valid JSON.',
            'timezone'                                           => in_array($value, timezone_identifiers_list(), true) ? null : 'Unknown timezone.',
            default                                              => mb_strlen($value) <= 2000 ? null : 'Too long (max 2000 characters).',
        };
    }

    private function payload(): array
    {
        $values = (new SettingModel())->allValues();

        foreach (SettingsSeeder::DEFAULTS as $key => $default) {
            $values[$key] ??= $default;
        }

        ksort($values);

        return ['settings' => $values, 'editable_keys' => array_keys(SettingsSeeder::DEFAULTS)];
    }
}
