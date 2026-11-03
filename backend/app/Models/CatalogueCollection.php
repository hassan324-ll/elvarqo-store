<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatalogueCollection extends Model
{
    use SoftDeletes;

    protected $table = 'catalogue_collections';

    protected $guarded = [];

    protected $hidden = ['image_webp'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_id ? url('/api/v1/staff/catalogue/category-images/'.$this->id.'/'.$this->image_id) : null;
    }

    public function publicImage(): array
    {
        return ['image_url' => $this->image_id ? url('/api/v1/catalogue/category-images/'.$this->id.'/'.$this->image_id) : null, 'image_alt' => $this->image_alt];
    }

    protected function casts(): array
    {
        return ['active' => 'boolean', 'version' => 'integer'];
    }
}
