# Contextual Permissions

> **Document:** 4 of 10  
> **Package:** `aiarmada/filament-authz`  
> **Status:** Vision

---

## Overview

Implement **context-aware permissions** that are scoped to specific teams, tenants, resources, or time periods—enabling granular access control beyond global permissions.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                 CONTEXTUAL PERMISSION LAYERS                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │              GLOBAL PERMISSIONS                             │ │
│  │         (User can create orders anywhere)                   │ │
│  └────────────────────────────────────────────────────────────┘ │
│                            │                                     │
│                            ▼                                     │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │              TENANT PERMISSIONS                             │ │
│  │    (User can create orders in specific tenant only)        │ │
│  └────────────────────────────────────────────────────────────┘ │
│                            │                                     │
│                            ▼                                     │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │              TEAM PERMISSIONS                               │ │
│  │      (User can create orders for their team only)          │ │
│  └────────────────────────────────────────────────────────────┘ │
│                            │                                     │
│                            ▼                                     │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │              RESOURCE PERMISSIONS                           │ │
│  │     (User can only edit their own orders)                  │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Permission Scope Types

### PermissionScope Enum

```php
enum PermissionScope: string
{
    case Global = 'global';
    case Tenant = 'tenant';
    case Team = 'team';
    case Department = 'department';
    case Resource = 'resource';
    case Owner = 'owner';
    case Temporal = 'temporal';

    public function label(): string
    {
        return match ($this) {
            self::Global => 'Global',
            self::Tenant => 'Tenant-scoped',
            self::Team => 'Team-scoped',
            self::Department => 'Department-scoped',
            self::Resource => 'Resource-specific',
            self::Owner => 'Owner-only',
            self::Temporal => 'Time-limited',
        };
    }
}
```

---

## Scoped Permission Assignment

### ScopedPermission Model

```php
final class ScopedPermission extends Model
{
    use HasUuids;

    protected $fillable = [
        'permission_id',
        'permissionable_type',
        'permissionable_id',
        'scope_type',
        'scope_id',
        'scope_model',
        'conditions',
        'granted_at',
        'expires_at',
        'granted_by',
    ];

    protected function casts(): array
    {
        return [
            'scope_type' => PermissionScope::class,
            'conditions' => 'array',
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function permissionable(): MorphTo
    {
        return $this->morphTo(); // User or Role
    }

    public function scope(): MorphTo
    {
        return $this->morphTo('scope', 'scope_model', 'scope_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * Check if this scoped permission is currently active.
     */
    public function isActive(): bool
    {
        if ($this->expires_at && now()->gt($this->expires_at)) {
            return false;
        }

        return true;
    }

    /**
     * Check if this permission applies to a given context.
     */
    public function appliesTo(array $context): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return match ($this->scope_type) {
            PermissionScope::Global => true,
            PermissionScope::Tenant => $this->matchesTenant($context),
            PermissionScope::Team => $this->matchesTeam($context),
            PermissionScope::Owner => $this->matchesOwner($context),
            default => $this->evaluateConditions($context),
        };
    }

    private function matchesTenant(array $context): bool
    {
        return ($context['tenant_id'] ?? null) === $this->scope_id;
    }

    private function matchesTeam(array $context): bool
    {
        return ($context['team_id'] ?? null) === $this->scope_id;
    }

    private function matchesOwner(array $context): bool
    {
        return ($context['owner_id'] ?? null) === $this->permissionable_id;
    }

    private function evaluateConditions(array $context): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        foreach ($this->conditions as $key => $value) {
            if (($context[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }
}
```

---

## Contextual Authorization Service

### ContextualAuthorizationService

```php
final class ContextualAuthorizationService
{
    public function __construct(
        private readonly PermissionAggregator $aggregator,
    ) {}

    /**
     * Check if user has permission in a specific context.
     */
    public function can(User $user, string $permission, array $context = []): bool
    {
        // 1. Check global permissions first
        if ($user->hasPermissionTo($permission)) {
            return true;
        }

        // 2. Check scoped permissions
        $scopedPermissions = ScopedPermission::query()
            ->where('permissionable_type', User::class)
            ->where('permissionable_id', $user->id)
            ->whereHas('permission', fn ($q) => $q->where('name', $permission))
            ->get();

        foreach ($scopedPermissions as $scoped) {
            if ($scoped->appliesTo($context)) {
                return true;
            }
        }

        // 3. Check role-based scoped permissions
        foreach ($user->roles as $role) {
            $roleScopedPermissions = ScopedPermission::query()
                ->where('permissionable_type', Role::class)
                ->where('permissionable_id', $role->id)
                ->whereHas('permission', fn ($q) => $q->where('name', $permission))
                ->get();

            foreach ($roleScopedPermissions as $scoped) {
                if ($scoped->appliesTo($context)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check owner-only permission.
     */
    public function canAsOwner(User $user, string $permission, Model $resource): bool
    {
        $ownerField = $this->getOwnerField($resource);

        return $this->can($user, $permission, [
            'owner_id' => $resource->{$ownerField},
            'resource_type' => get_class($resource),
            'resource_id' => $resource->getKey(),
        ]) && $resource->{$ownerField} === $user->id;
    }

    /**
     * Get permissions for a specific context.
     */
    public function getContextualPermissions(User $user, array $context): Collection
    {
        $permissions = collect();

        // Global permissions
        $permissions = $permissions->merge($user->getAllPermissions());

        // Scoped permissions
        $scoped = ScopedPermission::query()
            ->where('permissionable_type', User::class)
            ->where('permissionable_id', $user->id)
            ->with('permission')
            ->get()
            ->filter(fn ($sp) => $sp->appliesTo($context))
            ->pluck('permission');

        $permissions = $permissions->merge($scoped);

        return $permissions->unique('id');
    }

    private function getOwnerField(Model $resource): string
    {
        if (method_exists($resource, 'getOwnerField')) {
            return $resource->getOwnerField();
        }

        return 'user_id';
    }
}
```

---

## Team-Scoped Permissions

### TeamPermissionService

```php
final class TeamPermissionService
{
    /**
     * Grant permission to user within a team.
     */
    public function grantTeamPermission(
        User $user,
        string $permission,
        Team $team,
        ?DateTimeInterface $expiresAt = null
    ): ScopedPermission {
        $perm = Permission::findByName($permission);

        return ScopedPermission::create([
            'permission_id' => $perm->id,
            'permissionable_type' => User::class,
            'permissionable_id' => $user->id,
            'scope_type' => PermissionScope::Team,
            'scope_id' => $team->id,
            'scope_model' => Team::class,
            'granted_at' => now(),
            'expires_at' => $expiresAt,
            'granted_by' => auth()->id(),
        ]);
    }

    /**
     * Revoke team permission.
     */
    public function revokeTeamPermission(User $user, string $permission, Team $team): bool
    {
        return ScopedPermission::query()
            ->where('permissionable_type', User::class)
            ->where('permissionable_id', $user->id)
            ->where('scope_type', PermissionScope::Team)
            ->where('scope_id', $team->id)
            ->whereHas('permission', fn ($q) => $q->where('name', $permission))
            ->delete() > 0;
    }

    /**
     * Get all team-specific permissions for a user.
     */
    public function getTeamPermissions(User $user, Team $team): Collection
    {
        return ScopedPermission::query()
            ->where('permissionable_type', User::class)
            ->where('permissionable_id', $user->id)
            ->where('scope_type', PermissionScope::Team)
            ->where('scope_id', $team->id)
            ->with('permission')
            ->get()
            ->pluck('permission');
    }
}
```

---

## Temporal Permissions

### TemporalPermissionService

```php
final class TemporalPermissionService
{
    /**
     * Grant a time-limited permission.
     */
    public function grantTemporary(
        User $user,
        string $permission,
        DateTimeInterface $expiresAt,
        ?string $reason = null
    ): ScopedPermission {
        $perm = Permission::findByName($permission);

        return ScopedPermission::create([
            'permission_id' => $perm->id,
            'permissionable_type' => User::class,
            'permissionable_id' => $user->id,
            'scope_type' => PermissionScope::Temporal,
            'granted_at' => now(),
            'expires_at' => $expiresAt,
            'granted_by' => auth()->id(),
            'conditions' => [
                'reason' => $reason,
            ],
        ]);
    }

    /**
     * Extend a temporal permission.
     */
    public function extend(ScopedPermission $scopedPermission, DateTimeInterface $newExpiry): ScopedPermission
    {
        $scopedPermission->update([
            'expires_at' => $newExpiry,
        ]);

        return $scopedPermission;
    }

    /**
     * Get all expiring permissions in the next N days.
     */
    public function getExpiringSoon(int $days = 7): Collection
    {
        return ScopedPermission::query()
            ->where('scope_type', PermissionScope::Temporal)
            ->whereBetween('expires_at', [now(), now()->addDays($days)])
            ->with(['permission', 'permissionable'])
            ->get();
    }

    /**
     * Cleanup expired permissions.
     */
    public function cleanupExpired(): int
    {
        return ScopedPermission::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();
    }
}
```

---

## Owner-Only Trait

### HasOwnerPermissions Trait

```php
trait HasOwnerPermissions
{
    /**
     * Get the owner field name.
     */
    public function getOwnerField(): string
    {
        return $this->ownerField ?? 'user_id';
    }

    /**
     * Check if user is the owner.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->{$this->getOwnerField()} === $user->id;
    }

    /**
     * Scope to owner's records.
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where($this->getOwnerField(), $user->id);
    }

    /**
     * Scope to owner's records or has permission.
     */
    public function scopeAccessibleBy(Builder $query, User $user, string $permission): Builder
    {
        if ($user->can($permission)) {
            return $query;
        }

        return $query->where($this->getOwnerField(), $user->id);
    }
}
```

---

## Policy Integration

### Contextual Policy Example

```php
class OrderPolicy
{
    public function __construct(
        private readonly ContextualAuthorizationService $contextualAuth,
    ) {}

    public function view(User $user, Order $order): bool
    {
        // Global permission
        if ($user->can('orders.view')) {
            return true;
        }

        // Owner permission
        if ($order->isOwnedBy($user) && $user->can('orders.viewOwn')) {
            return true;
        }

        // Team permission
        return $this->contextualAuth->can($user, 'orders.view', [
            'team_id' => $order->team_id,
        ]);
    }

    public function update(User $user, Order $order): bool
    {
        // Global permission
        if ($user->can('orders.update')) {
            return true;
        }

        // Owner-only
        return $this->contextualAuth->canAsOwner($user, 'orders.updateOwn', $order);
    }
}
```

---

## Macros for Contextual Checks

```php
// Extend Action macros
Action::macro('requiresTeamPermission', function (string $permission, string $teamIdField = 'team_id'): static {
    return $this->authorize(function ($record) use ($permission, $teamIdField) {
        $context = ['team_id' => $record->{$teamIdField} ?? null];
        return app(ContextualAuthorizationService::class)
            ->can(auth()->user(), $permission, $context);
    });
});

Action::macro('requiresOwnership', function (string $ownerField = 'user_id'): static {
    return $this->authorize(function ($record) use ($ownerField) {
        return $record->{$ownerField} === auth()->id();
    });
});
```

---

## Navigation

**Previous:** [03-role-inheritance.md](03-role-inheritance.md)  
**Next:** [05-audit-trail.md](05-audit-trail.md)
