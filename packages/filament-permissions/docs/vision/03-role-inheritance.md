# Role Inheritance

> **Document:** 3 of 10  
> **Package:** `aiarmada/filament-permissions`  
> **Status:** Vision

---

## Overview

Implement **role templates and inheritance chains** enabling roles to inherit permissions from parent roles, reducing duplication and simplifying permission management.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    ROLE HIERARCHY                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│                    ┌─────────────┐                              │
│                    │ Super Admin │                              │
│                    │   (Root)    │                              │
│                    └──────┬──────┘                              │
│                           │                                      │
│           ┌───────────────┼───────────────┐                     │
│           │               │               │                     │
│           ▼               ▼               ▼                     │
│    ┌──────────┐    ┌──────────┐    ┌──────────┐                │
│    │  Admin   │    │ Manager  │    │ Finance  │                │
│    └────┬─────┘    └────┬─────┘    └────┬─────┘                │
│         │               │               │                       │
│    ┌────┴────┐    ┌────┴────┐         │                       │
│    │         │    │         │         │                       │
│    ▼         ▼    ▼         ▼         ▼                       │
│ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐                  │
│ │Staff │ │Editor│ │Sales │ │Supp. │ │Acct. │                  │
│ └──────┘ └──────┘ └──────┘ └──────┘ └──────┘                  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Role Template

### RoleTemplate Model

```php
final class RoleTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'is_system',
        'permissions',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'permissions' => 'array',
            'settings' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'template_id');
    }

    /**
     * Get all permissions including inherited from parents.
     */
    public function getAllPermissions(): array
    {
        $permissions = $this->permissions ?? [];

        if ($this->parent) {
            $parentPermissions = $this->parent->getAllPermissions();
            $permissions = array_unique(array_merge($parentPermissions, $permissions));
        }

        return $permissions;
    }

    /**
     * Get the inheritance chain.
     */
    public function getInheritanceChain(): Collection
    {
        $chain = collect([$this]);

        if ($this->parent) {
            $chain = $chain->merge($this->parent->getInheritanceChain());
        }

        return $chain;
    }

    /**
     * Create a role from this template.
     */
    public function createRole(string $guard = 'web'): Role
    {
        $role = Role::create([
            'name' => $this->name,
            'guard_name' => $guard,
            'template_id' => $this->id,
        ]);

        $role->syncPermissions($this->getAllPermissions());

        return $role;
    }
}
```

---

## Extended Role Model

### Role with Inheritance

```php
// Extend Spatie Role model
final class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'template_id',
        'parent_id',
        'level',
        'is_assignable',
        'max_users',
    ];

    protected function casts(): array
    {
        return [
            'is_assignable' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RoleTemplate::class, 'template_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Get all permissions including inherited.
     */
    public function getAllPermissions(): Collection
    {
        $directPermissions = $this->permissions;

        if ($this->parent) {
            $inheritedPermissions = $this->parent->getAllPermissions();
            return $directPermissions->merge($inheritedPermissions)->unique('id');
        }

        return $directPermissions;
    }

    /**
     * Get only directly assigned permissions (not inherited).
     */
    public function getDirectPermissions(): Collection
    {
        return $this->permissions;
    }

    /**
     * Get only inherited permissions.
     */
    public function getInheritedPermissions(): Collection
    {
        if (! $this->parent) {
            return collect();
        }

        return $this->parent->getAllPermissions();
    }

    /**
     * Check if role inherits from another.
     */
    public function inheritsFrom(Role $role): bool
    {
        $current = $this->parent;

        while ($current) {
            if ($current->id === $role->id) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }

    /**
     * Get the depth in the hierarchy.
     */
    public function getDepth(): int
    {
        $depth = 0;
        $current = $this->parent;

        while ($current) {
            $depth++;
            $current = $current->parent;
        }

        return $depth;
    }
}
```

---

## Role Inheritance Service

### RoleInheritanceService

```php
final class RoleInheritanceService
{
    /**
     * Set a role's parent (inheritance).
     */
    public function setParent(Role $role, Role $parent): Role
    {
        // Prevent circular inheritance
        if ($parent->inheritsFrom($role)) {
            throw new CircularInheritanceException(
                "Cannot set {$parent->name} as parent of {$role->name} - would create circular inheritance"
            );
        }

        // Prevent self-inheritance
        if ($role->id === $parent->id) {
            throw new CircularInheritanceException(
                "Role cannot inherit from itself"
            );
        }

        $role->parent_id = $parent->id;
        $role->level = $parent->level + 1;
        $role->save();

        // Clear permission cache
        $this->clearPermissionCache($role);

        event(new RoleInheritanceChanged($role, $parent));

        return $role;
    }

    /**
     * Remove inheritance (make role root-level).
     */
    public function removeParent(Role $role): Role
    {
        $role->parent_id = null;
        $role->level = 0;
        $role->save();

        $this->clearPermissionCache($role);

        event(new RoleInheritanceChanged($role, null));

        return $role;
    }

    /**
     * Clone a role with all its permissions.
     */
    public function cloneRole(Role $source, string $newName): Role
    {
        $newRole = Role::create([
            'name' => $newName,
            'guard_name' => $source->guard_name,
            'parent_id' => $source->parent_id,
            'level' => $source->level,
        ]);

        // Copy direct permissions only (inherited come automatically)
        $newRole->syncPermissions($source->getDirectPermissions());

        return $newRole;
    }

    /**
     * Promote a role (change parent to grandparent).
     */
    public function promoteRole(Role $role): Role
    {
        if (! $role->parent) {
            throw new RoleHierarchyException("Role is already at root level");
        }

        $grandparent = $role->parent->parent;
        
        if ($grandparent) {
            return $this->setParent($role, $grandparent);
        }

        return $this->removeParent($role);
    }

    /**
     * Get all descendants of a role.
     */
    public function getDescendants(Role $role): Collection
    {
        $descendants = collect();

        foreach ($role->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($this->getDescendants($child));
        }

        return $descendants;
    }

    /**
     * Recalculate levels for all roles.
     */
    public function recalculateLevels(): void
    {
        $rootRoles = Role::whereNull('parent_id')->get();

        foreach ($rootRoles as $role) {
            $this->setLevelRecursive($role, 0);
        }
    }

    private function setLevelRecursive(Role $role, int $level): void
    {
        $role->level = $level;
        $role->save();

        foreach ($role->children as $child) {
            $this->setLevelRecursive($child, $level + 1);
        }
    }

    private function clearPermissionCache(Role $role): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
```

---

## Permission Aggregation

### PermissionAggregator Service

```php
final class PermissionAggregator
{
    public function __construct(
        private readonly WildcardPermissionResolver $wildcardResolver,
        private readonly ImplicitPermissionService $implicitService,
    ) {}

    /**
     * Get all effective permissions for a role.
     */
    public function getEffectivePermissions(Role $role): Collection
    {
        $permissions = collect();

        // 1. Get inherited permissions
        if ($role->parent) {
            $permissions = $permissions->merge(
                $this->getEffectivePermissions($role->parent)
            );
        }

        // 2. Add direct permissions
        $permissions = $permissions->merge($role->permissions);

        // 3. Expand wildcards
        foreach ($role->permissions as $permission) {
            if (str_contains($permission->name, '*')) {
                $expanded = $this->wildcardResolver->expand($permission->name);
                foreach ($expanded as $name) {
                    $perm = Permission::where('name', $name)->first();
                    if ($perm) {
                        $permissions->push($perm);
                    }
                }
            }
        }

        // 4. Add implicit permissions
        foreach ($permissions->pluck('name') as $permName) {
            $implied = $this->implicitService->getImpliedPermissions($permName);
            foreach ($implied as $impliedName) {
                $perm = Permission::where('name', $impliedName)->first();
                if ($perm) {
                    $permissions->push($perm);
                }
            }
        }

        return $permissions->unique('id');
    }

    /**
     * Get permissions breakdown for a role.
     */
    public function getPermissionBreakdown(Role $role): array
    {
        return [
            'direct' => $role->getDirectPermissions(),
            'inherited' => $role->getInheritedPermissions(),
            'implicit' => $this->getImplicitPermissions($role),
            'wildcard' => $this->getWildcardExpanded($role),
            'total' => $this->getEffectivePermissions($role),
        ];
    }
}
```

---

## Role Template Configuration

```php
// config/filament-permissions.php
return [
    'role_templates' => [
        'super_admin' => [
            'name' => 'Super Admin',
            'description' => 'Full system access',
            'permissions' => ['*'],
            'is_system' => true,
        ],
        
        'admin' => [
            'name' => 'Admin',
            'parent' => 'super_admin',
            'description' => 'Administrative access',
            'permissions' => [
                'users.*',
                'roles.viewAny',
                'roles.view',
                'settings.*',
            ],
        ],
        
        'manager' => [
            'name' => 'Manager',
            'parent' => 'admin',
            'description' => 'Department manager access',
            'permissions' => [
                'orders.*',
                'inventory.*',
                'reports.view',
            ],
        ],
        
        'staff' => [
            'name' => 'Staff',
            'parent' => 'manager',
            'description' => 'Basic staff access',
            'permissions' => [
                'orders.viewAny',
                'orders.view',
                'orders.update',
            ],
        ],
    ],
];
```

---

## Hierarchy Visualization

```
Super Admin [*]
│
├── Admin [users.*, roles.view*, settings.*]
│   │
│   ├── Manager [orders.*, inventory.*, reports.view]
│   │   │
│   │   ├── Staff [orders.viewAny, orders.view, orders.update]
│   │   │
│   │   └── Warehouse [inventory.stock.*, inventory.locations.view*]
│   │
│   └── Editor [content.*, media.*]
│
└── Finance [payments.*, invoices.*, reports.*]
    │
    └── Accountant [invoices.viewAny, invoices.view, payments.view*]
```

---

## Navigation

**Previous:** [02-hierarchical-permissions.md](02-hierarchical-permissions.md)  
**Next:** [04-contextual-permissions.md](04-contextual-permissions.md)
