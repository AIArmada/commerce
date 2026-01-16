---
title: Usage
---

# Usage

## Protecting Resources

Filament Resources are protected automatically via **Laravel Policies**. The package discovers your resources and generates permission keys that match standard policy methods.

### Generated Permission Keys

For a `UserResource`, the following permissions are generated:

| Permission Key | Policy Method |
|----------------|---------------|
| `user.view-any` | `viewAny()` |
| `user.view` | `view()` |
| `user.create` | `create()` |
| `user.update` | `update()` |
| `user.delete` | `delete()` |
| `user.restore` | `restore()` |
| `user.force-delete` | `forceDelete()` |

### Generating Policies

Generate a policy for your models:

```bash
php artisan authz:policies --panel=admin
```

This generates a policy in `app/Policies` that checks the appropriate permissions:

```php
// Generated app/Policies/UserPolicy.php
class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('user.view-any');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('user.view');
    }

    public function create(User $user): bool
    {
        return $user->can('user.create');
    }

    // ... other methods
}
```

## Protecting Pages

Add the `HasPageAuthz` trait to your custom Filament Pages:

```php
namespace App\Filament\Pages;

use AIArmada\FilamentAuthz\Concerns\HasPageAuthz;
use Filament\Pages\Page;

class SystemSettings extends Page
{
    use HasPageAuthz;

    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static string $view = 'filament.pages.system-settings';
}
```

The page will automatically require the permission `page.system-settings` (derived from the class name).

### Custom Permission Key

Override the permission key if needed:

```php
class SystemSettings extends Page
{
    use HasPageAuthz;

    public static function authzPermission(): ?string
    {
        return 'admin.system-settings'; // Custom key
    }
}
```

## Protecting Widgets

Add the `HasWidgetAuthz` trait to your widgets:

```php
namespace App\Filament\Widgets;

use AIArmada\FilamentAuthz\Concerns\HasWidgetAuthz;
use Filament\Widgets\StatsOverviewWidget;

class RevenueWidget extends StatsOverviewWidget
{
    use HasWidgetAuthz;

    protected function getStats(): array
    {
        return [
            Stat::make('Revenue', '$192,000'),
        ];
    }
}
```

The widget will require the permission `widget.revenue-widget`.

## Custom Permissions

Define custom permissions beyond resources/pages/widgets in `config/filament-authz.php`:

```php
'custom_permissions' => [
    'export-reports' => 'Export Reports',
    'view-analytics' => 'View Analytics Dashboard',
    'manage-backups' => 'Manage System Backups',
    'impersonate-users', // Auto-generates label: "Impersonate Users"
],
```

These appear in the "Custom" tab of the Role resource and can be checked normally:

```php
if ($user->can('export-reports')) {
    // Allow export
}
```

## Programmatic Permission Building

Use the `Authz` facade to build and check permissions:

```php
use AIArmada\FilamentAuthz\Facades\Authz;

// Build a permission key using configured format
$key = Authz::buildPermissionKey('Order', 'delete');
// Returns: 'order.delete' (with kebab case + dot separator)

// Get all permissions for a resource
$permissions = Authz::getResourcePermissions(OrderResource::class);
// Returns: ['order.view-any' => 'View Any', 'order.view' => 'View', ...]

// Get all discovered permissions
$allPermissions = Authz::getAllPermissions();

// Check access
if (auth()->user()->can($key)) {
    // User can delete orders
}
```

### Custom Permission Key Builder

Override the default key builder for special cases:

```php
use AIArmada\FilamentAuthz\Facades\Authz;

Authz::buildPermissionKeyUsing(function (string $subject, string $action): string {
    return strtolower($subject) . ':' . strtolower($action);
});

// Now: Authz::buildPermissionKey('Order', 'Delete') returns 'order:delete'
```

## Wildcard Permissions

Grant broad access using wildcard patterns:

```php
// In a seeder or command
$role = Role::findOrCreate('order-manager', 'web');
$role->givePermissionTo('order.*');

// Now the user can:
$user->can('order.view');        // ✓ true
$user->can('order.create');      // ✓ true
$user->can('order.delete');      // ✓ true
$user->can('product.view');      // ✗ false
```

Wildcards support multiple patterns:

| Pattern | Matches |
|---------|---------|
| `*` | Everything |
| `order.*` | `order.view`, `order.create`, etc. |
| `*.view` | `order.view`, `product.view`, etc. |

## Super Admin Bypass

Users with the super admin role bypass **all** permission checks:

```php
// config/filament-authz.php
'super_admin_role' => 'super_admin',
```

Assign the role:

```bash
php artisan authz:super-admin --user=1
```

Or programmatically:

```php
$user->assignRole('super_admin');

// Now all checks pass
$user->can('anything.here'); // true
Gate::allows('any-ability'); // true
```
