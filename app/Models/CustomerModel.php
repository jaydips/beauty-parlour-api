<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Customers of the parlour.
 *
 * Phone is the natural key (10 digits, unique across soft-deleted rows too,
 * because the hosting SQLite has no partial unique indexes). Whether the app
 * soft-deletes or hard-deletes customers is still an open owner decision; the
 * model defaults to soft delete and findByPhone() can see deleted rows so a
 * re-registered number restores the old record instead of failing on the index.
 */
class CustomerModel extends Model
{
    protected $table          = 'customers';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['name', 'phone', 'alt_phone', 'gender', 'birthday', 'notes', 'whatsapp_opt_in'];
    protected $useTimestamps  = true;
    protected $dateFormat     = 'datetime';
    protected $useSoftDeletes = true;

    protected $validationRules = [
        'id'              => 'permit_empty|is_natural_no_zero', // placeholder for the is_unique rule on update
        'name'            => 'required|string|max_length[100]',
        'phone'           => 'required|regex_match[/^[0-9]{10}$/]|is_unique[customers.phone,id,{id}]',
        'alt_phone'       => 'permit_empty|regex_match[/^[0-9]{10}$/]',
        'gender'          => 'permit_empty|string|max_length[10]',
        'birthday'        => 'permit_empty|valid_date[Y-m-d]',
        'notes'           => 'permit_empty|string',
        'whatsapp_opt_in' => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [
        'phone' => [
            'regex_match' => 'Phone must be exactly 10 digits without country code.',
            'is_unique'   => 'A customer with this phone number already exists.',
        ],
    ];

    /**
     * Finds a customer by 10-digit phone, optionally including soft-deleted rows.
     */
    public function findByPhone(string $phone, bool $withDeleted = false): ?array
    {
        $builder = $withDeleted ? $this->withDeleted() : $this;

        return $builder->where('phone', $phone)->first();
    }

    /**
     * Restores a soft-deleted customer (used when the same phone is registered again).
     */
    public function restore(int $id): bool
    {
        return $this->builder()
            ->where($this->primaryKey, $id)
            ->update([$this->deletedField => null]);
    }
}
