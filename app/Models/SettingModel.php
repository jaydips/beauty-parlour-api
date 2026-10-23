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
