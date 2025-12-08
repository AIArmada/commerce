# Future: Auto-Enforcement Traits

> **Drop-in traits for automatic permission enforcement on Pages, Widgets, and Resources**

## Overview

Shield's `HasPageShield` and `HasWidgetShield` traits are elegant — simply add the trait and permissions are enforced. We enhance this pattern with more power and flexibility.

## Shield's Approach

```php
// Shield's HasPageShield
trait HasPageShield
{
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && parent::shouldRegisterNavigation();
    }

    public static function canAccess(): bool
    {
        $permission = static::getPagePermission();
        $user = Filament::auth()?->user();
        return $permission && $user ? $user->can($permission) : parent::canAccess();
    }
}
```

## Our Enhanced Implementation

### 1. HasPagePermissions Trait

```php
namespace AIArmada\FilamentPermissions\Traits;

use AIArmada\FilamentPermissions\Facades\Permissions;
use Filament\Facades\Filament;
use Illuminate\Support\Str;

trait HasPagePermissions
{
    protected static ?string $pagePermissionKey = null;
    protected static array $requiredPermissions = [];
    protected static array $requiredRoles = [];
    protected static bool $ownerOnly = false;
    protected static ?string $teamPermissionScope = null;
    
    /**
     * Boot the trait — register listeners if needed.
     */
    public static function bootHasPagePermissions(): void
    {
        // Log access attempts if audit is enabled
        if (config('filament-authz.features.audit')) {
            static::accessed(function () {
                Permissions::audit()->logPageAccess(static::class);
            });
        }
    }
    
    /**
     * Check if the page should appear in navigation.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && parent::shouldRegisterNavigation();
    }
    
    /**
     * Comprehensive access check with multiple strategies.
     */
    public static function canAccess(): bool
    {
        $user = Filament::auth()?->user();
        
        if (!$user) {
            return false;
        }
        
        // Super admin bypass
        if (Permissions::isSuperAdmin($user)) {
            return true;
        }
        
        // Role requirements
        if (!empty(static::$requiredRoles)) {
            if (!$user->hasAnyRole(static::$requiredRoles)) {
                return false;
            }
        }
        
        // Team scope check
        if (static::$teamPermissionScope) {
            $team = static::getTeamFromContext();
            if (!Permissions::team($team)->checkPermission($user, static::getPermissionKey())) {
                return false;
            }
            return true;
        }
        
        // Multiple permissions required
        if (!empty(static::$requiredPermissions)) {
            return Permissions::aggregator()->checkAllPermissions(
                $user,
                static::$requiredPermissions
            );
        }
        
        // Standard permission check with hierarchy
        return Permissions::aggregator()->checkPermission(
            $user,
            static::getPermissionKey()
        );
    }
    
    /**
     * Get the permission key for this page.
     */
    public static function getPermissionKey(): string
    {
        if (static::$pagePermissionKey) {
            return static::$pagePermissionKey;
        }
        
        $slug = static::getSlug() ?? Str::kebab(class_basename(static::class));
        return "page.{$slug}";
    }
    
    /**
     * Configure permission key.
     */
    public static function permissionKey(string $key): void
    {
        static::$pagePermissionKey = $key;
    }
    
    /**
     * Require specific permissions.
     */
    public static function requirePermissions(array $permissions): void
    {
        static::$requiredPermissions = $permissions;
    }
    
    /**
     * Require specific roles.
     */
    public static function requireRoles(array $roles): void
    {
        static::$requiredRoles = $roles;
    }
    
    /**
     * Scope to team permissions.
     */
    public static function scopeToTeam(string $teamIdKey = 'team_id'): void
    {
        static::$teamPermissionScope = $teamIdKey;
    }
    
    /**
     * Get page title with permission badge (for debugging).
     */
    public function getTitleWithPermissionDebug(): string
    {
        if (!app()->isLocal()) {
            return $this->getTitle();
        }
        
        return $this->getTitle() . " [{$this->getPermissionKey()}]";
    }
}
```

### 2. HasWidgetPermissions Trait

```php
namespace AIArmada\FilamentPermissions\Traits;

trait HasWidgetPermissions
{
    protected static ?string $widgetPermissionKey = null;
    protected static array $requiredPermissions = [];
    protected static array $requiredRoles = [];
    protected static ?string $teamScope = null;
    protected static bool $hideWhenUnauthorized = true;
    
    /**
     * Check if widget can be rendered.
     */
    public static function canView(): bool
    {
        $user = Filament::auth()?->user();
        
        if (!$user) {
            return false;
        }
        
        if (Permissions::isSuperAdmin($user)) {
            return true;
        }
        
        // Role check
        if (!empty(static::$requiredRoles) && !$user->hasAnyRole(static::$requiredRoles)) {
            return false;
        }
        
        // Team scope
        if (static::$teamScope) {
            return Permissions::team(static::getCurrentTeamId())
                ->checkPermission($user, static::getPermissionKey());
        }
        
        // Permission check with aggregation
        if (!empty(static::$requiredPermissions)) {
            return Permissions::aggregator()->checkAllPermissions(
                $user,
                static::$requiredPermissions
            );
        }
        
        return Permissions::aggregator()->checkPermission(
            $user,
            static::getPermissionKey()
        );
    }
    
    /**
     * Get permission key using naming convention.
     */
    public static function getPermissionKey(): string
    {
        if (static::$widgetPermissionKey) {
            return static::$widgetPermissionKey;
        }
        
        $name = Str::snake(class_basename(static::class));
        return "widget.{$name}";
    }
    
    /**
     * Override render to check permissions.
     */
    public function render(): \Illuminate\View\View
    {
        if (!static::canView()) {
            return static::$hideWhenUnauthorized
                ? view('filament-authz::widgets.unauthorized')
                : view('filament-authz::widgets.placeholder');
        }
        
        return parent::render();
    }
    
    /**
     * Configure widget visibility behavior.
     */
    public static function showPlaceholderWhenUnauthorized(): void
    {
        static::$hideWhenUnauthorized = false;
    }
}
```

### 3. HasResourcePermissions Trait

```php
namespace AIArmada\FilamentPermissions\Traits;

trait HasResourcePermissions
{
    use HasOwnerPermissions;
    
    protected static array $customAbilities = [];
    protected static ?string $permissionPrefix = null;
    
    /**
     * Define custom abilities beyond CRUD.
     */
    public static function abilities(array $abilities): void
    {
        static::$customAbilities = $abilities;
    }
    
    /**
     * Get all abilities for this resource.
     */
    public static function getAllAbilities(): array
    {
        return array_merge(
            ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'],
            static::$customAbilities
        );
    }
    
    /**
     * Get permission for specific ability.
     */
    public static function getPermissionFor(string $ability): string
    {
        $prefix = static::$permissionPrefix ?? Str::snake(class_basename(static::getModel()));
        return "{$prefix}.{$ability}";
    }
    
    /**
     * Check if user can perform ability.
     */
    public static function canPerform(string $ability, ?Model $record = null): bool
    {
        $user = Filament::auth()?->user();
        
        if (!$user) {
            return false;
        }
        
        if (Permissions::isSuperAdmin($user)) {
            return true;
        }
        
        // Owner check for record-specific abilities
        if ($record && static::hasOwnerPermissions()) {
            if (static::isOwner($user, $record)) {
                return in_array($ability, static::$ownerAbilities);
            }
        }
        
        return Permissions::aggregator()->checkPermission(
            $user,
            static::getPermissionFor($ability),
            ['resource' => $record]
        );
    }
    
    /**
     * Override table filters based on permissions.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        // Apply team scope if configured
        if (static::$teamScope && $teamId = static::getCurrentTeamId()) {
            $query->where('team_id', $teamId);
        }
        
        // Apply owner-only filter if user lacks viewAny but has owner permissions
        if (static::$restrictToOwned) {
            $user = Filament::auth()?->user();
            if ($user && !Permissions::can($user, static::getPermissionFor('viewAny'))) {
                $query->where(static::$ownerColumn, $user->id);
            }
        }
        
        return $query;
    }
}
```

### 4. HasPanelPermissions Trait

```php
namespace AIArmada\FilamentPermissions\Traits;

/**
 * Add to User model for automatic panel access control.
 */
trait HasPanelPermissions
{
    public static function bootHasPanelPermissions(): void
    {
        // Auto-assign panel user role on creation
        if (config('filament-authz.panel_user_role')) {
            static::created(function ($user) {
                $user->assignRole(config('filament-authz.panel_user_role'));
            });
            
            static::deleting(function ($user) {
                $user->removeRole(config('filament-authz.panel_user_role'));
            });
        }
    }
    
    /**
     * Check if user can access a specific panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Super admin has access to all panels
        if ($this->hasRole(config('filament-authz.super_admin_role'))) {
            return true;
        }
        
        // Check panel-specific roles
        $panelRoles = config("filament-authz.panel_roles.{$panel->getId()}", []);
        
        if (!empty($panelRoles)) {
            return $this->hasAnyRole($panelRoles);
        }
        
        // Fallback to panel user role
        if ($panelUserRole = config('filament-authz.panel_user_role')) {
            return $this->hasRole($panelUserRole);
        }
        
        return false;
    }
    
    /**
     * Get panels this user can access.
     */
    public function getAccessiblePanels(): Collection
    {
        return collect(Filament::getPanels())
            ->filter(fn ($panel) => $this->canAccessPanel($panel));
    }
}
```

### 5. HasTemporalPermissions Trait

```php
namespace AIArmada\FilamentPermissions\Traits;

/**
 * For resources that support time-based access control.
 */
trait HasTemporalPermissions
{
    protected static bool $checkTemporalGrants = true;
    
    /**
     * Check if user has temporal grant for ability.
     */
    public static function hasTemporalAccess(string $ability, ?Model $record = null): bool
    {
        if (!static::$checkTemporalGrants) {
            return false;
        }
        
        $user = Filament::auth()?->user();
        
        return Permissions::temporal()->hasActiveGrant(
            $user,
            static::getPermissionFor($ability),
            [
                'resource_type' => static::getModel(),
                'resource_id' => $record?->id,
            ]
        );
    }
    
    /**
     * Extend canPerform to check temporal grants.
     */
    public static function canPerform(string $ability, ?Model $record = null): bool
    {
        // Check temporal grant first
        if (static::hasTemporalAccess($ability, $record)) {
            return true;
        }
        
        return parent::canPerform($ability, $record);
    }
}
```

### 6. Usage Examples

```php
// Simple page protection
class SettingsPage extends Page
{
    use HasPagePermissions;
    
    // Auto-generates permission: page.settings
}

// Custom permission key
class ReportsPage extends Page
{
    use HasPagePermissions;
    
    protected static ?string $pagePermissionKey = 'reports.view';
}

// Multiple permissions required
class FinanceDashboard extends Page
{
    use HasPagePermissions;
    
    protected static array $requiredPermissions = [
        'finance.view',
        'reports.access',
    ];
}

// Role-based access
class AdminSettings extends Page
{
    use HasPagePermissions;
    
    protected static array $requiredRoles = ['Super Admin', 'Admin'];
}

// Team-scoped widget
class TeamStatsWidget extends Widget
{
    use HasWidgetPermissions;
    
    protected static ?string $teamScope = 'team_id';
}

// Resource with custom abilities
class OrderResource extends Resource
{
    use HasResourcePermissions;
    
    protected static array $customAbilities = [
        'approve',
        'ship',
        'refund',
        'export',
    ];
}

// User model with panel access
class User extends Authenticatable
{
    use HasRoles, HasPanelPermissions;
}
```

### 7. Configuration

```php
// config/filament-authz.php
return [
    'traits' => [
        // Auto-apply traits to discovered entities
        'auto_apply' => false,
        
        // Default behavior
        'pages' => [
            'hide_navigation_when_unauthorized' => true,
            'log_access_attempts' => true,
        ],
        
        'widgets' => [
            'hide_when_unauthorized' => true,
            'show_placeholder' => false,
        ],
        
        'resources' => [
            'owner_abilities' => ['view', 'update', 'delete'],
            'owner_column' => 'user_id',
            'restrict_to_owned' => false,
        ],
    ],
];
```

## Comparison with Shield

| Feature | Shield | Our Traits |
|---------|--------|------------|
| **Page protection** | Basic | Full (roles, multiple permissions, teams) |
| **Widget protection** | Basic | Full + placeholder support |
| **Resource protection** | Via policy | Direct + policy integration |
| **Panel access** | HasPanelShield | Enhanced with multi-panel support |
| **Owner permissions** | ❌ | ✅ Built-in |
| **Team scoping** | ❌ | ✅ Built-in |
| **Temporal grants** | ❌ | ✅ Built-in |
| **Audit logging** | ❌ | ✅ Integrated |
| **Aggregation** | ❌ | ✅ Uses permission aggregator |
| **Debug mode** | ❌ | ✅ Permission key display |
