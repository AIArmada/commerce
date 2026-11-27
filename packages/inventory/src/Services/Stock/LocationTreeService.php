<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services\Stock;

use AIArmada\Inventory\Features\Stock\Actions\SaveInventoryLocation;
use AIArmada\Inventory\Enums\TemperatureZone;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class LocationTreeService
{
    /**
     * Get a tree structure of all locations.
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getTree(): Collection
    {
        $locations = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
            ->orderBy('depth')
            ->orderBy('name')
            ->get();

        return $this->buildTree($locations);
    }

    /**
     * Get a tree structure for active locations only.
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getActiveTree(): Collection
    {
        $locations = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
            ->active()
            ->orderBy('depth')
            ->orderBy('name')
            ->get();

        return $this->buildTree($locations);
    }

    /**
     * Get the subtree starting from a location.
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getSubtree(InventoryLocation $root): Collection
    {
        $rootIsAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query()->whereKey($root->getKey()))->exists();

        if (! $rootIsAllowed) {
            throw new InvalidArgumentException('Invalid root location for current owner');
        }

        $locations = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
            ->where(function (Builder $query) use ($root): void {
                $query->where('id', $root->id)
                    ->orWhere('path', 'like', $root->path . '/%');
            })
            ->orderBy('depth')
            ->orderBy('name')
            ->get();

        return $this->buildTree($locations, $root->parent_id);
    }

    /**
     * Get a flattened tree with indentation info.
     *
     * @return array<array{id: string, name: string, code: string, depth: int, is_active: bool, children_count: int}>
     */
    public function getFlatTree(): array
    {
        return InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
            ->withCount('children')
            ->orderByRaw('COALESCE(path, id)')
            ->get()
            ->map(fn (InventoryLocation $loc): array => [
                'id' => $loc->id,
                'name' => $loc->name,
                'code' => $loc->code,
                'depth' => $loc->depth,
                'is_active' => $loc->is_active,
                'children_count' => $loc->children_count,
            ])
            ->toArray();
    }

    /**
     * Get options for select fields with hierarchy indication.
     *
     * @return array<string, string>
     */
    public function getSelectOptions(): array
    {
        return InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
            ->active()
            ->orderByRaw('COALESCE(path, id)')
            ->get()
            ->mapWithKeys(fn (InventoryLocation $loc): array => [
                $loc->id => str_repeat('— ', $loc->depth) . $loc->name . ' (' . $loc->code . ')',
            ])
            ->toArray();
    }

    /**
     * Create a new location within the hierarchy.
     */
    public function createLocation(
        string $name,
        string $code,
        ?InventoryLocation $parent = null,
        ?TemperatureZone $zone = null,
        bool $isHazmatCertified = false
    ): InventoryLocation {
        return app(SaveInventoryLocation::class)->create([
            'name' => $name,
            'code' => $code,
            'parent_id' => $parent?->id,
            'is_active' => true,
            'temperature_zone' => $zone?->value,
            'is_hazmat_certified' => $isHazmatCertified,
        ]);
    }

    /**
     * Move a location to a new parent.
     */
    public function moveLocation(InventoryLocation $location, ?InventoryLocation $newParent): InventoryLocation
    {
        return app(SaveInventoryLocation::class)->update($location, ['parent_id' => $newParent?->id]);
    }

    /**
     * Rebuild all paths in the hierarchy (for maintenance/repair).
     */
    public function rebuildAllPaths(): int
    {
        return DB::transaction(function (): int {
            $count = 0;

            // First, handle root locations
            $roots = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                ->whereNull('parent_id')
                ->get();

            foreach ($roots as $root) {
                $root->path = $root->id;
                $root->depth = 0;
                $root->saveQuietly();
                $count++;

                $count += $this->rebuildChildPaths($root);
            }

            return $count;
        });
    }

    /**
     * Get all leaf locations (endpoints suitable for storing inventory).
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getLeafLocations(): Collection
    {
        $query = InventoryLocation::leaves()
            ->active()
            ->orderBy('path');

        InventoryOwnerScope::applyToLocationQuery($query);

        return $query->get();
    }

    /**
     * Get locations at a specific depth level.
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getLocationsAtDepth(int $depth): Collection
    {
        $query = InventoryLocation::atDepth($depth)
            ->active()
            ->orderBy('name');

        InventoryOwnerScope::applyToLocationQuery($query);

        return $query->get();
    }

    /**
     * Get the maximum depth in the hierarchy.
     */
    public function getMaxDepth(): int
    {
        return (int) InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())->max('depth');
    }

    /**
     * Validate that a location can be moved to a new parent.
     *
     * @return array{valid: bool, reason: string|null}
     */
    public function validateMove(InventoryLocation $location, ?InventoryLocation $newParent): array
    {
        if ($newParent === null) {
            return ['valid' => true, 'reason' => null];
        }

        if ($location->id === $newParent->id) {
            return ['valid' => false, 'reason' => 'Cannot move a location to itself'];
        }

        if ($location->isAncestorOf($newParent)) {
            return ['valid' => false, 'reason' => 'Cannot move a location to its own descendant'];
        }

        // Check temperature zone compatibility
        if ($location->temperature_zone !== null && $newParent->temperature_zone !== null) {
            $locationZone = TemperatureZone::from($location->temperature_zone);
            $parentZone = TemperatureZone::from($newParent->temperature_zone);

            if (! $locationZone->isCompatibleWith($parentZone)) {
                return [
                    'valid' => false,
                    'reason' => sprintf(
                        'Temperature zone mismatch: %s cannot be placed within %s',
                        $locationZone->label(),
                        $parentZone->label()
                    ),
                ];
            }
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * Build a tree from a flat collection.
     *
     * @param  Collection<int, InventoryLocation>  $locations
     * @return Collection<int, InventoryLocation>
     */
    private function buildTree(Collection $locations, ?string $parentId = null): Collection
    {
        $tree = new Collection;

        $roots = $locations->filter(fn (InventoryLocation $loc): bool => $loc->parent_id === $parentId);

        foreach ($roots as $root) {
            $root->setRelation('children', $this->buildTree($locations, $root->id));
            $tree->push($root);
        }

        return $tree;
    }

    /**
     * Recursively rebuild child paths.
     */
    private function rebuildChildPaths(InventoryLocation $parent): int
    {
        $count = 0;
        $children = InventoryLocation::where('parent_id', $parent->id)->get();

        foreach ($children as $child) {
            $child->path = $parent->path . '/' . $child->id;
            $child->depth = $parent->depth + 1;
            $child->saveQuietly();
            $count++;

            $count += $this->rebuildChildPaths($child);
        }

        return $count;
    }
}
