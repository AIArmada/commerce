<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Models\Concerns;

use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin InventoryLocation
 *
 * @property string|null $parent_id
 * @property string|null $path
 * @property int $depth
 * @property-read InventoryLocation|null $parent
 * @property-read Collection<int, InventoryLocation> $children
 * @property-read Collection<int, InventoryLocation> $descendants
 * @property-read Collection<int, InventoryLocation> $ancestors
 */
trait HasLocationHierarchy
{
    /**
     * Get the parent location.
     *
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'parent_id');
    }

    /**
     * Get direct children.
     *
     * @return HasMany<InventoryLocation, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(InventoryLocation::class, 'parent_id');
    }

    /**
     * Get all descendants.
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getDescendantsAttribute(): Collection
    {
        if ($this->path === null) {
            return new Collection;
        }

        return $this->hierarchyQuery()
            ->where('path', 'like', $this->path . '/%')
            ->orderBy('depth')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all ancestors.
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getAncestorsAttribute(): Collection
    {
        if ($this->path === null || $this->depth === 0) {
            return new Collection;
        }

        $ancestorIds = explode('/', $this->path);
        array_pop($ancestorIds); // Remove self

        if (empty($ancestorIds)) {
            return new Collection;
        }

        return $this->hierarchyQuery()
            ->whereIn('id', $ancestorIds)
            ->orderBy('depth')
            ->get();
    }

    /**
     * Get the root ancestor.
     */
    public function getRoot(): ?InventoryLocation
    {
        if ($this->isRoot()) {
            return $this;
        }

        if ($this->path === null) {
            return null;
        }

        $rootId = explode('/', $this->path)[0];

        return $this->hierarchyQuery()
            ->whereKey($rootId)
            ->first();
    }

    /**
     * Check if this location is a root node.
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Check if this location is a leaf node (has no children).
     */
    public function isLeaf(): bool
    {
        return ! $this->children()->exists();
    }

    /**
     * Check if this location is an ancestor of another.
     */
    public function isAncestorOf(InventoryLocation $location): bool
    {
        if ($location->path === null || $this->path === null) {
            return false;
        }

        return $this->sharesHierarchyOwner($location) && str_starts_with($location->path, $this->path . '/');
    }

    /**
     * Check if this location is a descendant of another.
     */
    public function isDescendantOf(InventoryLocation $location): bool
    {
        return $location->isAncestorOf($this);
    }

    /**
     * Check if this location is a sibling of another.
     */
    public function isSiblingOf(InventoryLocation $location): bool
    {
        return $this->sharesHierarchyOwner($location)
            && $this->parent_id === $location->parent_id
            && $this->id !== $location->id;
    }

    /**
     * Get siblings (locations with same parent).
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getSiblings(): Collection
    {
        return $this->hierarchyQuery()
            ->where('parent_id', $this->parent_id)
            ->where('id', '!=', $this->id)
            ->orderBy('name')
            ->get();
    }

    /** @return Builder<InventoryLocation> */
    private function hierarchyQuery(): Builder
    {
        $query = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query());

        if (! InventoryOwnerScope::isEnabled()) {
            return $query;
        }

        return $query->where('owner_type', $this->owner_type)
            ->where('owner_id', $this->owner_id);
    }

    private function sharesHierarchyOwner(InventoryLocation $location): bool
    {
        return ! InventoryOwnerScope::isEnabled()
            || ($this->owner_type === $location->owner_type
                && (string) $this->owner_id === (string) $location->owner_id);
    }

    /**
     * Scope query to root locations only.
     *
     * @param  Builder<InventoryLocation>  $query
     * @return Builder<InventoryLocation>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope query to leaf locations only.
     *
     * @param  Builder<InventoryLocation>  $query
     * @return Builder<InventoryLocation>
     */
    public function scopeLeaves(Builder $query): Builder
    {
        return $query->whereDoesntHave('children');
    }

    /**
     * Scope query to a specific depth.
     *
     * @param  Builder<InventoryLocation>  $query
     * @return Builder<InventoryLocation>
     */
    public function scopeAtDepth(Builder $query, int $depth): Builder
    {
        return $query->where('depth', $depth);
    }

    /**
     * Scope query to locations within a subtree.
     *
     * @param  Builder<InventoryLocation>  $query
     * @return Builder<InventoryLocation>
     */
    public function scopeWithinSubtree(Builder $query, InventoryLocation $ancestor): Builder
    {
        if (InventoryOwnerScope::isEnabled()) {
            $query->where('owner_type', $ancestor->owner_type)
                ->where('owner_id', $ancestor->owner_id);
        }

        return $query->where(function (Builder $q) use ($ancestor): void {
            $q->where('id', $ancestor->id);

            if ($ancestor->path !== null && $ancestor->path !== '') {
                $q->orWhere('path', 'like', $ancestor->path . '/%');
            }
        });
    }

    /**
     * Get breadcrumb trail (ancestors + self).
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getBreadcrumbs(): Collection
    {
        $ancestors = $this->ancestors;
        $ancestors->push($this);

        return $ancestors;
    }
}
