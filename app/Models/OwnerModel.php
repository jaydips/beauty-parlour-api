<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * The single parlour owner (profile only in Phase 1; credentials arrive in Phase 2).
 * The application keeps exactly one row with id = 1.
 */
class OwnerModel extends Model
{
    public const OWNER_ID = 1;

    protected $table         = 'owner';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'phone', 'email'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';

    protected $validationRules = [
        'name'  => 'required|string|max_length[100]',
        'phone' => 'required|regex_match[/^[0-9]{10}$/]',
        'email' => 'permit_empty|valid_email|max_length[190]',
    ];

    protected $validationMessages = [
        'phone' => ['regex_match' => 'Phone must be exactly 10 digits without country code.'],
    ];

    /**
     * Returns the owner row or null when the profile has not been created yet.
     */
    public function getOwner(): ?array
    {
        return $this->find(self::OWNER_ID);
    }

    /**
     * Creates or updates the single owner row.
     *
     * @param array<string, mixed> $data
     */
    public function saveOwner(array $data): bool
    {
        $data['id'] = self::OWNER_ID;

        if ($this->getOwner() === null) {
            return $this->insert($data) !== false;
        }

        return $this->update(self::OWNER_ID, $data);
    }
}
