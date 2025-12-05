# Filament Authorization Integration

> **Document:** Supplemental Vision  
> **Package:** `aiarmada/filament-permissions`  
> **Status:** Vision - Filament Deep Integration

---

## Overview

Deep integration with **FilamentPHP's authorization architecture** across all component types—Actions, Resources, Pages, Navigation, Columns, Filters, Schema Components, and Widgets—providing declarative, permission-aware visibility and authorization.

---

## Filament Authorization Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│              FILAMENT AUTHORIZATION LAYERS                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Panel Level                                                    │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  • strictAuthorization() - require policies               │ │
│  │  • authGuard() - authentication guard                     │ │
│  │  • Panel middleware - role-based access                   │ │
│  └────────────────────────────────────────────────────────────┘ │
│                            │                                     │
│                            ▼                                     │
│  Resource Level                                                 │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  • HasAuthorization trait                                  │ │
│  │  • canViewAny, canCreate, canEdit, canDelete...           │ │
│  │  • Policy integration via Gate                             │ │
│  └────────────────────────────────────────────────────────────┘ │
│                            │                                     │
│                            ▼                                     │
│  Component Level                                                │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  Actions: CanBeAuthorized, CanBeHidden                     │ │
│  │  Columns: CanBeHidden, CanBeToggled                        │ │
│  │  Filters: CanBeHidden                                      │ │
│  │  Schema:  CanBeHidden (forms, infolists)                   │ │
│  │  Navigation: visible(), hidden()                           │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Enhanced Action Macros

### Current vs Vision

```php
// Current (basic)
Action::macro('requiresPermission', function (string $permission): static {
    return $this
        ->authorize($permission)
        ->visible(fn () => auth()->user()?->can($permission));
});

// Vision (enhanced)
Action::macro('requiresPermission', function (
    string | array $permissions,
    string $logic = 'all', // all, any
    ?string $tooltip = null
): static {
    $permissions = Arr::wrap($permissions);

    return $this
        ->authorize(fn () => match ($logic) {
            'any' => Gate::any($permissions, $this->getRecord()),
            default => Gate::check($permissions, $this->getRecord()),
        })
        ->visible(fn () => match ($logic) {
            'any' => auth()->user()?->canAny($permissions),
            default => auth()->user()?->can($permissions[0]) && 
                       collect($permissions)->every(fn ($p) => auth()->user()?->can($p)),
        })
        ->when(filled($tooltip), fn ($action) => $action->authorizationTooltip()->authorizationMessage($tooltip));
});
```

### New Macro Suite

```php
// Contextual permission with scope
Action::macro('requiresScopedPermission', function (
    string $permission,
    string $scopeKey = 'team_id',
    ?Model $scopeModel = null
): static {
    return $this
        ->authorize(function ($record) use ($permission, $scopeKey, $scopeModel) {
            $scopeId = $record?->{$scopeKey} ?? $scopeModel?->getKey();
            
            return app(ContextualAuthorizationService::class)->can(
                auth()->user(),
                $permission,
                ['team_id' => $scopeId]
            );
        })
        ->visible(function ($record) use ($permission, $scopeKey, $scopeModel) {
            $scopeId = $record?->{$scopeKey} ?? $scopeModel?->getKey();
            
            return app(ContextualAuthorizationService::class)->can(
                auth()->user(),
                $permission,
                ['team_id' => $scopeId]
            );
        });
});

// Owner-only action
Action::macro('requiresOwnership', function (string $ownerField = 'user_id'): static {
    return $this
        ->authorize(fn ($record) => $record?->{$ownerField} === auth()->id())
        ->visible(fn ($record) => $record?->{$ownerField} === auth()->id());
});

// Hierarchical permission
Action::macro('requiresHierarchicalPermission', function (string $permission): static {
    return $this
        ->authorize(function ($record) use ($permission) {
            return app(PermissionAggregator::class)
                ->userHasPermission(auth()->user(), $permission);
        })
        ->visible(function () use ($permission) {
            return app(PermissionAggregator::class)
                ->userHasPermission(auth()->user(), $permission);
        });
});

// ABAC policy-based
Action::macro('requiresPolicy', function (string $policyName): static {
    return $this
        ->authorize(function ($record) use ($policyName) {
            $ability = $this->getName();
            return app(PolicyEngine::class)->can(auth()->user(), $ability, $record);
        })
        ->visible(function ($record) use ($policyName) {
            $ability = $this->getName();
            return app(PolicyEngine::class)->can(auth()->user(), $ability, $record);
        });
});
```

---

## Column Authorization

### CanBeAuthorized Trait for Columns

```php
namespace Aiarmada\FilamentPermissions\Columns\Concerns;

use Closure;
use Illuminate\Support\Facades\Gate;

trait CanBeAuthorized
{
    protected string | array | Closure | null $columnPermission = null;
    protected bool | Closure $shouldHideWhenUnauthorized = true;

    /**
     * Require permission to view this column.
     */
    public function requiresPermission(string | array | Closure $permission): static
    {
        $this->columnPermission = $permission;
        
        $this->visible(function ($record) use ($permission) {
            $permission = $this->evaluate($permission);
            
            if (is_array($permission)) {
                return Gate::any($permission, $record);
            }
            
            return Gate::check($permission, $record);
        });

        return $this;
    }

    /**
     * Require role to view this column.
     */
    public function requiresRole(string | array $roles): static
    {
        $roles = Arr::wrap($roles);
        
        $this->visible(fn () => auth()->user()?->hasAnyRole($roles));

        return $this;
    }

    /**
     * Show column only to owner.
     */
    public function ownerOnly(string $ownerField = 'user_id'): static
    {
        $this->visible(fn ($record) => $record?->{$ownerField} === auth()->id());

        return $this;
    }

    /**
     * Show column based on contextual permission.
     */
    public function requiresScopedPermission(
        string $permission,
        string $scopeKey = 'team_id'
    ): static {
        $this->visible(function ($record) use ($permission, $scopeKey) {
            return app(ContextualAuthorizationService::class)->can(
                auth()->user(),
                $permission,
                [$scopeKey => $record?->{$scopeKey}]
            );
        });

        return $this;
    }
}
```

### Usage Examples

```php
TextColumn::make('salary')
    ->money()
    ->requiresPermission('employees.viewSalary'),

TextColumn::make('notes')
    ->ownerOnly(),

TextColumn::make('commission')
    ->requiresRole(['sales_manager', 'admin']),

TextColumn::make('team_budget')
    ->requiresScopedPermission('teams.viewBudget', 'team_id'),
```

---

## Filter Authorization

### CanBeAuthorized Trait for Filters

```php
namespace Aiarmada\FilamentPermissions\Filters\Concerns;

trait CanBeAuthorized
{
    protected string | array | Closure | null $filterPermission = null;

    /**
     * Require permission to use this filter.
     */
    public function requiresPermission(string | array $permission): static
    {
        $this->filterPermission = $permission;
        
        $this->visible(fn () => auth()->user()?->can($permission));

        return $this;
    }

    /**
     * Require role to use this filter.
     */
    public function requiresRole(string | array $roles): static
    {
        $roles = Arr::wrap($roles);
        
        $this->visible(fn () => auth()->user()?->hasAnyRole($roles));

        return $this;
    }

    /**
     * Show filter only for scoped access.
     */
    public function forScopedAccess(string $permission, string $scopeType = 'team'): static
    {
        $this->visible(function () use ($permission, $scopeType) {
            return ScopedPermission::query()
                ->where('permissionable_type', User::class)
                ->where('permissionable_id', auth()->id())
                ->where('scope_type', $scopeType)
                ->whereHas('permission', fn ($q) => $q->where('name', $permission))
                ->exists();
        });

        return $this;
    }
}
```

### Usage Examples

```php
SelectFilter::make('status')
    ->options(OrderStatus::class)
    ->requiresPermission('orders.filter'),

SelectFilter::make('assigned_to')
    ->relationship('assignee', 'name')
    ->requiresRole('manager'),

TrashedFilter::make()
    ->requiresPermission('orders.viewTrashed'),
```

---

## Schema Component Authorization

### Form Field Authorization

```php
namespace Aiarmada\FilamentPermissions\Schemas\Concerns;

trait CanBeAuthorized
{
    protected string | array | Closure | null $fieldPermission = null;
    protected string | array | Closure | null $editPermission = null;

    /**
     * Require permission to view this field.
     */
    public function requiresViewPermission(string | array $permission): static
    {
        $this->visible(fn ($record) => auth()->user()?->can($permission, $record));

        return $this;
    }

    /**
     * Require permission to edit this field (view-only otherwise).
     */
    public function requiresEditPermission(string $permission): static
    {
        $this->editPermission = $permission;
        
        $this->disabled(fn ($record) => ! auth()->user()?->can($permission, $record));

        return $this;
    }

    /**
     * Different field behavior based on role.
     */
    public function forRoles(array $config): static
    {
        // $config = ['admin' => ['required' => true], 'editor' => ['disabled' => true]]
        foreach ($config as $role => $settings) {
            if (auth()->user()?->hasRole($role)) {
                foreach ($settings as $method => $value) {
                    $this->{$method}($value);
                }
                break;
            }
        }

        return $this;
    }

    /**
     * Mask field value for unauthorized users.
     */
    public function maskedUnless(string $permission, string $mask = '••••••••'): static
    {
        if (! auth()->user()?->can($permission)) {
            $this->formatStateUsing(fn () => $mask);
            $this->disabled();
        }

        return $this;
    }
}
```

### Usage Examples

```php
TextInput::make('ssn')
    ->requiresViewPermission('employees.viewSensitive')
    ->maskedUnless('employees.viewSsn'),

TextInput::make('salary')
    ->requiresEditPermission('employees.editSalary'),

Select::make('role')
    ->forRoles([
        'super_admin' => ['required' => true, 'searchable' => true],
        'admin' => ['disabled' => false],
        'manager' => ['disabled' => true],
    ]),
```

---

## Navigation Authorization

### Enhanced Navigation Macros

```php
namespace Aiarmada\FilamentPermissions\Navigation;

class AuthorizedNavigation
{
    /**
     * Register navigation macros.
     */
    public static function register(): void
    {
        NavigationItem::macro('requiresPermission', function (string | array $permission): static {
            $permission = Arr::wrap($permission);
            
            return $this->visible(fn () => auth()->user()?->canAny($permission));
        });

        NavigationItem::macro('requiresRole', function (string | array $roles): static {
            $roles = Arr::wrap($roles);
            
            return $this->visible(fn () => auth()->user()?->hasAnyRole($roles));
        });

        NavigationItem::macro('requiresAnyPermission', function (array $permissions): static {
            return $this->visible(fn () => auth()->user()?->canAny($permissions));
        });

        NavigationItem::macro('requiresAllPermissions', function (array $permissions): static {
            return $this->visible(function () use ($permissions) {
                return collect($permissions)->every(fn ($p) => auth()->user()?->can($p));
            });
        });

        NavigationItem::macro('forSuperAdmin', function (): static {
            return $this->visible(fn () => auth()->user()?->hasRole(
                config('filament-permissions.super_admin_role', 'super_admin')
            ));
        });

        NavigationGroup::macro('requiresAnyPermission', function (array $permissions): static {
            return $this->visible(fn () => auth()->user()?->canAny($permissions));
        });
    }
}
```

### Usage in Panel Provider

```php
->navigation(function (NavigationBuilder $builder): NavigationBuilder {
    return $builder
        ->items([
            NavigationItem::make('Dashboard')
                ->icon('heroicon-o-home')
                ->url('/admin'),
            
            NavigationItem::make('Orders')
                ->icon('heroicon-o-shopping-cart')
                ->requiresPermission('orders.viewAny'),
            
            NavigationItem::make('Settings')
                ->icon('heroicon-o-cog')
                ->requiresRole('admin'),
            
            NavigationItem::make('System')
                ->icon('heroicon-o-server')
                ->forSuperAdmin(),
        ])
        ->groups([
            NavigationGroup::make('Reports')
                ->items([...])
                ->requiresAnyPermission(['reports.view', 'analytics.view']),
        ]);
})
```

---

## Resource Authorization Enhancement

### Enhanced HasAuthorization Trait

```php
namespace Aiarmada\FilamentPermissions\Resources\Concerns;

trait HasEnhancedAuthorization
{
    /**
     * Check hierarchical permission (including wildcards).
     */
    public static function canHierarchical(string $action, ?Model $record = null): bool
    {
        $permission = static::getPermissionName($action);
        
        return app(PermissionAggregator::class)
            ->userHasPermission(auth()->user(), $permission, $record);
    }

    /**
     * Check contextual permission.
     */
    public static function canInContext(string $action, ?Model $record = null, array $context = []): bool
    {
        $permission = static::getPermissionName($action);
        
        // Auto-detect context from record
        if ($record && empty($context)) {
            $context = static::extractContext($record);
        }
        
        return app(ContextualAuthorizationService::class)
            ->can(auth()->user(), $permission, $context);
    }

    /**
     * Check ABAC policy.
     */
    public static function canByPolicy(string $action, ?Model $record = null): bool
    {
        return app(PolicyEngine::class)
            ->can(auth()->user(), $action, $record);
    }

    /**
     * Get permission name for action.
     */
    protected static function getPermissionName(string $action): string
    {
        $modelName = Str::snake(class_basename(static::getModel()));
        
        return "{$modelName}.{$action}";
    }

    /**
     * Extract context from record.
     */
    protected static function extractContext(Model $record): array
    {
        $context = [];
        
        if (method_exists($record, 'team')) {
            $context['team_id'] = $record->team_id;
        }
        
        if (method_exists($record, 'tenant')) {
            $context['tenant_id'] = $record->tenant_id;
        }
        
        $context['owner_id'] = $record->user_id ?? $record->owner_id ?? null;
        
        return $context;
    }
}
```

---

## Widget Authorization

### CanBeAuthorized Trait for Widgets

```php
namespace Aiarmada\FilamentPermissions\Widgets\Concerns;

trait CanBeAuthorized
{
    protected static string | array | null $widgetPermission = null;
    protected static string | array | null $widgetRoles = null;

    /**
     * Set required permission for widget.
     */
    public static function requiresPermission(string | array $permission): void
    {
        static::$widgetPermission = $permission;
    }

    /**
     * Set required roles for widget.
     */
    public static function requiresRole(string | array $roles): void
    {
        static::$widgetRoles = $roles;
    }

    /**
     * Check if user can view this widget.
     */
    public static function canView(): bool
    {
        if (static::$widgetPermission) {
            $permissions = Arr::wrap(static::$widgetPermission);
            if (! auth()->user()?->canAny($permissions)) {
                return false;
            }
        }

        if (static::$widgetRoles) {
            $roles = Arr::wrap(static::$widgetRoles);
            if (! auth()->user()?->hasAnyRole($roles)) {
                return false;
            }
        }

        return true;
    }
}
```

### Usage in Widgets

```php
class RevenueStatsWidget extends StatsOverviewWidget
{
    use CanBeAuthorized;

    protected static ?string $pollingInterval = '30s';

    public static function boot(): void
    {
        static::requiresPermission('analytics.viewRevenue');
    }

    protected function getStats(): array
    {
        if (! static::canView()) {
            return [];
        }

        return [
            Stat::make('Revenue', '$45,000'),
        ];
    }
}
```

---

## Page Authorization Enhancement

### Enhanced CanAuthorizeAccess

```php
namespace Aiarmada\FilamentPermissions\Pages\Concerns;

trait CanAuthorizeAccess
{
    protected static string | array | null $pagePermission = null;
    protected static string | array | null $pageRoles = null;

    /**
     * Set required permission for page.
     */
    protected static function permission(string | array $permission): void
    {
        static::$pagePermission = $permission;
    }

    /**
     * Set required roles for page.
     */
    protected static function roles(string | array $roles): void
    {
        static::$pageRoles = $roles;
    }

    /**
     * Enhanced canAccess with multiple authorization strategies.
     */
    public static function canAccess(): bool
    {
        // Check permission
        if (static::$pagePermission) {
            $permissions = Arr::wrap(static::$pagePermission);
            if (! auth()->user()?->canAny($permissions)) {
                return false;
            }
        }

        // Check roles
        if (static::$pageRoles) {
            $roles = Arr::wrap(static::$pageRoles);
            if (! auth()->user()?->hasAnyRole($roles)) {
                return false;
            }
        }

        // Check ABAC policies
        if (method_exists(static::class, 'getPagePolicy')) {
            $policy = static::getPagePolicy();
            if (! app(PolicyEngine::class)->can(auth()->user(), 'access', $policy)) {
                return false;
            }
        }

        return true;
    }
}
```

---

## Bulk Action Authorization

### Enhanced Bulk Actions

```php
namespace Aiarmada\FilamentPermissions\Actions\Concerns;

trait CanAuthorizeBulk
{
    protected bool | Closure $shouldAuthorizeEach = false;

    /**
     * Authorize each record individually before including in bulk.
     */
    public function authorizeEachRecord(bool | Closure $condition = true): static
    {
        $this->shouldAuthorizeEach = $condition;

        return $this;
    }

    /**
     * Filter selected records by authorization.
     */
    protected function getAuthorizedRecords(Collection $records): Collection
    {
        if (! $this->evaluate($this->shouldAuthorizeEach)) {
            return $records;
        }

        $permission = $this->getRequiredPermission();

        return $records->filter(function ($record) use ($permission) {
            return auth()->user()?->can($permission, $record);
        });
    }

    /**
     * Require owner for bulk action.
     */
    public function ownerOnlyBulk(string $ownerField = 'user_id'): static
    {
        return $this->authorize(function (Collection $records) use ($ownerField) {
            return $records->every(fn ($r) => $r->{$ownerField} === auth()->id());
        });
    }
}
```

---

## Integration Service Provider

```php
namespace Aiarmada\FilamentPermissions;

class FilamentAuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register Action macros
        $this->registerActionMacros();
        
        // Register Column traits via macros
        $this->registerColumnMacros();
        
        // Register Filter macros
        $this->registerFilterMacros();
        
        // Register Navigation macros
        AuthorizedNavigation::register();
        
        // Register Schema macros
        $this->registerSchemaMacros();
    }

    protected function registerActionMacros(): void
    {
        Action::macro('requiresPermission', /* ... */);
        Action::macro('requiresScopedPermission', /* ... */);
        Action::macro('requiresOwnership', /* ... */);
        Action::macro('requiresHierarchicalPermission', /* ... */);
        Action::macro('requiresPolicy', /* ... */);
        
        BulkAction::macro('authorizeEachRecord', /* ... */);
        BulkAction::macro('ownerOnlyBulk', /* ... */);
    }

    protected function registerColumnMacros(): void
    {
        Column::macro('requiresPermission', /* ... */);
        Column::macro('requiresRole', /* ... */);
        Column::macro('ownerOnly', /* ... */);
        Column::macro('requiresScopedPermission', /* ... */);
    }

    protected function registerFilterMacros(): void
    {
        BaseFilter::macro('requiresPermission', /* ... */);
        BaseFilter::macro('requiresRole', /* ... */);
        BaseFilter::macro('forScopedAccess', /* ... */);
    }

    protected function registerSchemaMacros(): void
    {
        Field::macro('requiresViewPermission', /* ... */);
        Field::macro('requiresEditPermission', /* ... */);
        Field::macro('forRoles', /* ... */);
        Field::macro('maskedUnless', /* ... */);
    }
}
```

---

## Summary: Filament Component Coverage

| Component | Macros/Traits | Capabilities |
|-----------|--------------|--------------|
| **Actions** | `requiresPermission`, `requiresScopedPermission`, `requiresOwnership`, `requiresHierarchicalPermission`, `requiresPolicy` | Permission, scope, owner, hierarchy, ABAC |
| **BulkActions** | `authorizeEachRecord`, `ownerOnlyBulk` | Per-record auth, owner filtering |
| **Columns** | `requiresPermission`, `requiresRole`, `ownerOnly`, `requiresScopedPermission` | Hide/show based on access |
| **Filters** | `requiresPermission`, `requiresRole`, `forScopedAccess` | Filter availability |
| **Schema** | `requiresViewPermission`, `requiresEditPermission`, `forRoles`, `maskedUnless` | Field visibility, editability, masking |
| **Navigation** | `requiresPermission`, `requiresRole`, `requiresAnyPermission`, `requiresAllPermissions`, `forSuperAdmin` | Menu visibility |
| **Widgets** | `CanBeAuthorized` trait | Widget visibility |
| **Pages** | `CanAuthorizeAccess` enhancement | Page access control |
| **Resources** | `HasEnhancedAuthorization` | Hierarchical, contextual, ABAC |

---

## Navigation

**Related:** [01-executive-summary.md](01-executive-summary.md)  
**Related:** [04-contextual-permissions.md](04-contextual-permissions.md)  
**Related:** [06-policy-evolution.md](06-policy-evolution.md)
