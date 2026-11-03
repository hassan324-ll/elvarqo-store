<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogueMedia extends Model
{
    protected $table = 'catalogue_media';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sizes' => 'array'];
    }

    public $incrementing = false;

    protected $keyType = 'string';
}
