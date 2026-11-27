<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class UpdateInventoryLevel
{
    /** @param array<string, mixed> $attributes */
    public function execute(InventoryLevel $level, array $attributes): InventoryLevel
    {
        if (InventoryOwnerScope::isEnabled()) {
            OwnerContext::assertResolvedOrExplicitGlobal(
                InventoryOwnerScope::resolveOwner(),
                'Inventory mutations require an owner context or explicit global context.',
            );
        }

        return DB::transaction(function () use ($level, $attributes): InventoryLevel {
            $record = InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
                ->whereKey($level->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $settings = Arr::only($attributes, ['quantity_on_hand', 'reorder_point', 'allocation_strategy']);

            if (array_key_exists('quantity_on_hand', $settings) && (int) $settings['quantity_on_hand'] < $record->quantity_reserved) {
                throw new InvalidArgumentException('On-hand quantity cannot be less than reserved stock.');
            }

            $record->update($settings);

            return $record;
        });
    }
}
