<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogueVariant extends Model
{
    protected $table = 'catalogue_variants';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'measurements' => 'array', 'price_minor' => 'integer', 'compare_at_minor' => 'integer'];
    }
}
