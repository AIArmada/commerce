<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $parent_id
 * @property array<string>|null $implicit_abilities
 * @property int $sort_order
 * @property bool $is_system
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PermissionGroup|null $parent
 * @property-read Collection<int, PermissionGroup> $children
 * @property-read Collection<int, Permission> $permissions
 */
class PermissionGroup extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'implicit_abilities',
        'sort_order',
        'is_system',
    ];

    public function getTable(): string
    {
        /** @var string $table */
        $table = config('filament-authz.database.tables.permission_groups', 'authz_permission_groups');

        return $table;
    }

    /**
     * @return BelongsTo<PermissionGroup, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<PermissionGroup, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        /** @var string $pivotTable */
        $pivotTable = config('filament-authz.database.tables.permission_group_permission', 'authz_permission_group_permission');

        return $this->belongsToMany(
            Permission::class,
            $pivotTable,
            'permission_group_id',
            'permission_id'
        );
    }

    /**
     * Get all permissions including those from parent groups.
     *
     * @return Collection<int, Permission>
     */
    public function getAllPermissions(): Collection
    {
        $permissions = $this->permissions;

        if ($this->parent) {
            $permissions = $permissions->merge($this->parent->getAllPermissions());
        }

        return $permissions->unique('id');
    }

    /**
     * Get all ancestor groups.
     *
     * @return Collection<int, PermissionGroup>
     */
    public function getAncestors(): Collection
    {
        $ancestors = new Collection;
        $current = $this->parent;

        while ($current !== null) {
            $ancestors->push($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * Get all descendant groups.
     *
     * @return Collection<int, PermissionGroup>
     */
    public function getDescendants(): Collection
    {
        $descendants = new Collection;

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }

        return $descendants;
    }

    /**
     * Check if this group is an ancestor of another group.
     */
    public function isAncestorOf(self $group): bool
    {
        return $group->getAncestors()->contains('id', $this->id);
    }

    /**
     * Check if this group is a descendant of another group.
     */
    public function isDescendantOf(self $group): bool
    {
        return $this->getAncestors()->contains('id', $group->id);
    }

    /**
     * Get the depth of this group in the hierarchy.
     */
    public function getDepth(): int
    {
        return $this->getAncestors()->count();
    }

    /**
     * Check if this group is a root group (no parent).
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Check if this group is a leaf group (no children).
     */
    public function isLeaf(): bool
    {
        return $this->children()->count() === 0;
    }

    protected static function booted(): void
    {
        static::deleting(function (PermissionGroup $group): void {
            // Reassign children to parent
            self::query()
                ->where('parent_id', $group->id)
                ->update(['parent_id' => $group->parent_id]);

            // Detach permissions
            $group->permissions()->detach();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'implicit_abilities' => 'array',
            'sort_order' => 'integer',
            'is_system' => 'boolean',
        ];
    }
}
