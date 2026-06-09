<?php

declare(strict_types=1);

namespace AIArmada\Products\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait IsOptionEntity
{
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    protected function resolveProductTable(string $key, string $default): string
    {
        $tables = config('products.database.tables', []);
        $prefix = config('products.database.table_prefix', 'product_');

        return $tables[$key] ?? $prefix . $default;
    }
}
