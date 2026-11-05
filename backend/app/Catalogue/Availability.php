<?php

namespace App\Catalogue;

class Availability
{
    public static function tracked(): bool
    {
        return (bool) config('catalogue.track_stock', false);
    }

    public static function inStock(int $available, bool $active = true): bool
    {
        return $active && (! self::tracked() || $available > 0);
    }
}
