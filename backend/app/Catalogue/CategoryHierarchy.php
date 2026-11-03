<?php

namespace App\Catalogue;

use App\Models\CatalogueCollection;

class CategoryHierarchy
{
    public static function ids(int $id, bool $public = false): array
    {
        $group = CatalogueCollection::findOrFail($id);
        $ids = [$id];
        if ($group->kind === 'collection') {
            $ids = array_merge($ids, CatalogueCollection::where('kind', 'category')->where('parent_id', $id)->when($public, fn ($q) => $q->where('active', true))->pluck('id')->all());
        }

        return $ids;
    }

    public static function visible($query)
    {
        return $query->where('active', true)->where(function ($q) {
            $q->where(fn ($q) => $q->where('kind', 'collection')->whereNull('parent_id'))
                ->orWhere(fn ($q) => $q->where('kind', 'category')->whereIn('parent_id', CatalogueCollection::where('kind', 'collection')->whereNull('parent_id')->where('active', true)->select('id')));
        });
    }
}
