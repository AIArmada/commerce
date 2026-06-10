<?php

declare(strict_types=1);

namespace AIArmada\Products\Concerns;

use AIArmada\Products\Enums\Visibility;
use Illuminate\Database\Eloquent\Builder;

trait IsAttributeEntity
{
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visibility', Visibility::Visible);
    }

    protected function resolveProductTable(string $key, string $default): string
    {
        $tables = config('products.database.tables', []);
        $prefix = config('products.database.table_prefix', 'product_');

        return $tables[$key] ?? $prefix . $default;
    }
}
