<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class SaveInventoryLocation
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): InventoryLocation
    {
        return DB::transaction(function () use ($attributes): InventoryLocation {
            $query = $this->ownedQuery();
            $this->assertParent($query, $attributes);

            $location = new InventoryLocation($this->editable($attributes));

            if (InventoryOwnerScope::isEnabled()) {
                $owner = InventoryOwnerScope::resolveOwner();
                $location->owner_type = $owner?->getMorphClass();
                $location->owner_id = $owner?->getKey();
            }

            $location->save();

            return $location;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(InventoryLocation $location, array $attributes): InventoryLocation
    {
        return DB::transaction(function () use ($location, $attributes): InventoryLocation {
            $query = $this->ownedQuery();

            if (array_key_exists('parent_id', $attributes)) {
                (clone $query)->orderBy('id')->lockForUpdate()->pluck('id');
                $this->assertParent($query, $attributes);
            }

            $locked = $query->whereKey($location->getKey())->lockForUpdate()->firstOrFail();
            $locked->update($this->editable($attributes));

            return $locked;
        }, 3);
    }

    public function delete(InventoryLocation $location): bool
    {
        return DB::transaction(function () use ($location): bool {
            $query = $this->ownedQuery();
            (clone $query)->orderBy('id')->lockForUpdate()->pluck('id');
            $locked = $query->whereKey($location->getKey())->firstOrFail();

            return (bool) $locked->delete();
        }, 3);
    }

    /** @return Builder<InventoryLocation> */
    private function ownedQuery(): Builder
    {
        $query = InventoryLocation::query();

        if (InventoryOwnerScope::isEnabled()) {
            $owner = InventoryOwnerScope::resolveOwner();
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
            $query->forOwner($owner, false);
        }

        return $query;
    }

    /** @param Builder<InventoryLocation> $query
     * @param array<string, mixed> $attributes
     */
    private function assertParent(Builder $query, array $attributes): void
    {
        if (isset($attributes['parent_id'])) {
            (clone $query)->whereKey($attributes['parent_id'])->lockForUpdate()->firstOrFail();
        }
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function editable(array $attributes): array
    {
        return Arr::except($attributes, ['id', 'owner_type', 'owner_id', 'path', 'depth']);
    }
}
