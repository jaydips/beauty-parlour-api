<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * The parlour's price list. Soft-deleted so appointment history keeps its references.
 */
class ServiceModel extends Model
{
    protected $table          = 'services';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['name', 'category', 'duration_minutes', 'price', 'is_active', 'sort_order', 'notes'];
    protected $useTimestamps  = true;
    protected $dateFormat     = 'datetime';
    protected $useSoftDeletes = true;

    protected $validationRules = [
        'name'             => 'required|string|max_length[100]',
        'category'         => 'permit_empty|string|max_length[50]',
        'duration_minutes' => 'required|is_natural_no_zero|less_than_equal_to[1440]',
        'price'            => 'required|is_natural',
        'is_active'        => 'permit_empty|in_list[0,1]',
        'sort_order'       => 'permit_empty|integer',
        'notes'            => 'permit_empty|string',
    ];

    /**
     * Active services in display order (for the booking screen).
     */
    public function active(): array
    {
        return $this->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
