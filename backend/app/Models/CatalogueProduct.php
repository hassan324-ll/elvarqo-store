<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatalogueProduct extends Model
{
    use SoftDeletes;

    protected $table = 'catalogue_products';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['measurements' => 'array', 'version' => 'integer', 'published_at' => 'datetime', 'first_published_at' => 'datetime'];
    }
}
