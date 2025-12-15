# Hierarchical Permissions

> **Document:** 2 of 10  
> **Package:** `aiarmada/filament-authz`  
> **Status:** Vision

---

## Overview

Transform flat permission lists into **hierarchical permission trees** with wildcard support, implicit inheritance, and grouped management.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    PERMISSION TREE                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                    WILDCARD RESOLVER                        ││
│  │   orders.* → [viewAny, view, create, update, delete]       ││
│  └─────────────────────────────────────────────────────────────┘│
│                            │                                     │
│                            ▼                                     │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                    PERMISSION GROUPS                        ││
│  │                                                              ││
│  │  orders                    inventory                         ││
│  │  ├── viewAny               ├── locations                    ││
│  │  ├── view                  │   ├── viewAny                  ││
│  │  ├── create                │   ├── create                   ││
│  │  ├── update                │   └── manage                   ││
│  │  ├── delete                └── stock                        ││
│  │  └── export                    ├── viewAny                  ││
│  │                                ├── adjust                   ││
│  │                                └── transfer                 ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Permission Naming Convention

### Standard Structure

```
{domain}.{subdomain}.{ability}
```

### Examples

| Permission | Domain | Subdomain | Ability |
|------------|--------|-----------|---------|
| `orders.viewAny` | orders | - | viewAny |
| `orders.items.update` | orders | items | update |
| `inventory.locations.create` | inventory | locations | create |
| `inventory.stock.adjust` | inventory | stock | adjust |

---

## Wildcard Support

### WildcardPermissionResolver

```php
final class WildcardPermissionResolver
{
    /**
     * Expand a wildcard permission to all matching permissions.
     *
     * @return array<string>
     */
    public function expand(string $pattern): array
    {
        if (! str_contains($pattern, '*')) {
            return [$pattern];
        }

        $allPermissions = $this->getAllPermissions();
        $regex = $this->patternToRegex($pattern);

        return array_filter(
            $allPermissions,
            fn ($permission) => preg_match($regex, $permission)
        );
    }

    /**
     * Check if a permission matches a pattern.
     */
    public function matches(string $permission, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if ($pattern === $permission) {
            return true;
        }

        // orders.* matches orders.view, orders.create, etc.
        if (str_ends_with($pattern, '.*')) {
            $prefix = substr($pattern, 0, -2);
            return str_starts_with($permission, $prefix . '.');
        }

        // orders.*.view matches orders.items.view, orders.notes.view
        $regex = $this->patternToRegex($pattern);
        return (bool) preg_match($regex, $permission);
    }

    private function patternToRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '/');
        $regex = str_replace('\*', '[^.]+', $escaped);
        return '/^' . $regex . '$/';
    }

    private function getAllPermissions(): array
    {
        return Cache::remember('all_permissions', 3600, function () {
            return Permission::pluck('name')->toArray();
        });
    }
}
```

---

## Permission Group

### PermissionGroup Model

```php
final class PermissionGroup extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'icon',
        'sort_order',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'group_id');
    }

    /**
     * Get all permissions including from child groups.
     */
    public function allPermissions(): Collection
    {
        $permissions = $this->permissions;

        foreach ($this->children as $child) {
            $permissions = $permissions->merge($child->allPermissions());
        }

        return $permissions;
    }

    /**
     * Generate the wildcard pattern for this group.
     */
    public function getWildcardPattern(): string
    {
        $path = $this->getPath();
        return $path . '.*';
    }

    /**
     * Get the full path of this group.
     */
    public function getPath(): string
    {
        if ($this->parent) {
            return $this->parent->getPath() . '.' . $this->slug;
        }

        return $this->slug;
    }
}
```

---

## Implicit Permissions

### ImplicitPermissionService

```php
final class ImplicitPermissionService
{
    /**
     * Define implicit permission relationships.
     */
    private array $implicitMap = [
        'manage' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'update' => ['view'],
        'delete' => ['view'],
        'export' => ['viewAny'],
    ];

    /**
     * Get all permissions implied by a given permission.
     */
    public function getImpliedPermissions(string $permission): array
    {
        $parts = explode('.', $permission);
        $ability = array_pop($parts);
        $domain = implode('.', $parts);

        if (! isset($this->implicitMap[$ability])) {
            return [];
        }

        return array_map(
            fn ($implied) => $domain . '.' . $implied,
            $this->implicitMap[$ability]
        );
    }

    /**
     * Check if a user has a permission (including implicit).
     */
    public function hasPermission(User $user, string $permission): bool
    {
        // Direct permission check
        if ($user->can($permission)) {
            return true;
        }

        // Check if any permission implies this one
        $userPermissions = $user->getAllPermissions()->pluck('name');

        foreach ($userPermissions as $userPermission) {
            $implied = $this->getImpliedPermissions($userPermission);
            if (in_array($permission, $implied)) {
                return true;
            }
        }

        return false;
    }
}
```

---

## Permission Definition DSL

### Fluent Permission Builder

```php
final class PermissionBuilder
{
    private string $domain;
    private array $abilities = [];
    private ?string $guard = null;

    public static function for(string $domain): self
    {
        $builder = new self();
        $builder->domain = $domain;
        return $builder;
    }

    public function guard(string $guard): self
    {
        $this->guard = $guard;
        return $this;
    }

    public function withCrud(): self
    {
        return $this->abilities(['viewAny', 'view', 'create', 'update', 'delete']);
    }

    public function abilities(array $abilities): self
    {
        $this->abilities = array_merge($this->abilities, $abilities);
        return $this;
    }

    public function ability(string $ability): self
    {
        $this->abilities[] = $ability;
        return $this;
    }

    /**
     * @return array<Permission>
     */
    public function create(): array
    {
        $permissions = [];
        $guard = $this->guard ?? config('filament-authz.default_guard');

        foreach ($this->abilities as $ability) {
            $permissions[] = Permission::findOrCreate(
                "{$this->domain}.{$ability}",
                $guard
            );
        }

        return $permissions;
    }
}

// Usage
PermissionBuilder::for('orders')
    ->withCrud()
    ->ability('export')
    ->ability('cancel')
    ->create();

PermissionBuilder::for('inventory.stock')
    ->abilities(['viewAny', 'view', 'adjust', 'transfer'])
    ->create();
```

---

## Permission Registry

### PermissionRegistry Service

```php
final class PermissionRegistry
{
    /**
     * @var array<string, PermissionDefinition>
     */
    private array $definitions = [];

    /**
     * Register a permission definition.
     */
    public function define(string $name, array $options = []): self
    {
        $this->definitions[$name] = new PermissionDefinition(
            name: $name,
            description: $options['description'] ?? null,
            group: $options['group'] ?? $this->inferGroup($name),
            implies: $options['implies'] ?? [],
            guard: $options['guard'] ?? null,
        );

        return $this;
    }

    /**
     * Register multiple permissions for a domain.
     */
    public function domain(string $domain, array $abilities, array $options = []): self
    {
        foreach ($abilities as $ability) {
            $this->define("{$domain}.{$ability}", [
                'group' => $options['group'] ?? $domain,
                ...$options,
            ]);
        }

        return $this;
    }

    /**
     * Sync all registered permissions to database.
     */
    public function sync(): void
    {
        foreach ($this->definitions as $definition) {
            $permission = Permission::findOrCreate(
                $definition->name,
                $definition->guard ?? config('filament-authz.default_guard')
            );

            if ($definition->group) {
                $group = PermissionGroup::firstOrCreate(['slug' => $definition->group]);
                $permission->group_id = $group->id;
                $permission->save();
            }
        }
    }

    private function inferGroup(string $name): string
    {
        $parts = explode('.', $name);
        return $parts[0];
    }
}
```

---

## Configuration

```php
// config/filament-authz.php
return [
    'hierarchy' => [
        // Enable wildcard permission matching
        'wildcards' => true,
        
        // Enable implicit permission inference
        'implicit_permissions' => true,
        
        // Implicit permission map
        'implicit_map' => [
            'manage' => ['viewAny', 'view', 'create', 'update', 'delete'],
            'update' => ['view'],
            'delete' => ['view'],
    ],

    'groups' => [
        'orders' => [
            'label' => 'Order Management',
            'icon' => 'heroicon-o-shopping-cart',
            'permissions' => ['viewAny', 'view', 'create', 'update', 'delete', 'export'],
        ],
        'inventory' => [
            'label' => 'Inventory',
            'icon' => 'heroicon-o-cube',
            'children' => [
                'locations' => ['viewAny', 'view', 'create', 'update', 'delete'],
                'stock' => ['viewAny', 'view', 'adjust', 'transfer'],
            ],
        ],
    ],
];
```

---

## API Usage

```php
// Wildcard assignment
$role->givePermissionTo('orders.*');

// Check with wildcard
if ($user->hasPermissionTo('orders.*')) {
    // Has all order permissions
}

// Implied permissions
$role->givePermissionTo('orders.manage');
// User can now also: viewAny, view, create, update, delete

// Group-based assignment
$group = PermissionGroup::where('slug', 'inventory')->first();
$role->syncPermissions($group->allPermissions());
```

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-role-inheritance.md](03-role-inheritance.md)
