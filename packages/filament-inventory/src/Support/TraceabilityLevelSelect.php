<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Support;

use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

final class TraceabilityLevelSelect
{
    public static function make(): Select
    {
        return Select::make('inventory_level_id')
            ->label('Inventory item and location')
            ->visibleOn('create')
            ->required(fn (string $operation): bool => $operation === 'create')
            ->dehydrated(fn (string $operation): bool => $operation === 'create')
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => self::query()
                ->where(function (Builder $query) use ($search): void {
                    $query->whereRaw('CAST(inventoryable_id AS TEXT) LIKE ?', ['%' . $search . '%'])
                        ->orWhere('inventoryable_type', 'like', '%' . $search . '%')
                        ->orWhereHas('location', fn (Builder $location): Builder => $location->where('name', 'like', '%' . $search . '%'));
                })
                ->orderBy('id')
                ->limit(50)
                ->get()
                ->mapWithKeys(fn (InventoryLevel $level): array => [$level->id => self::label($level)])
                ->all())
            ->getOptionLabelUsing(function ($value): ?string {
                $level = self::query()->whereKey($value)->first();

                return $level === null ? null : self::label($level);
            });
    }

    /** @return Builder<InventoryLevel> */
    private static function query(): Builder
    {
        $query = InventoryLevel::query()->with('location');

        if (InventoryOwnerScope::isEnabled()) {
            $query->forOwner(InventoryOwnerScope::resolveOwner(), false);
        }

        return $query;
    }

    private static function label(InventoryLevel $level): string
    {
        return class_basename($level->inventoryable_type) . ' ' . $level->inventoryable_id . ' — ' . $level->location->name;
    }
}
