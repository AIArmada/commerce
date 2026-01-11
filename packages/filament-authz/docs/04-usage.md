---
title: Usage
---

# Usage

## Protecting Resources

To protect a Filament Resource, use the `HasResourceAuthz` trait (optional, as the plugin handles standard checks) or ensure your `getEloquentQuery` is scoped if using multitenancy.

Most authorization is handled automatically if you use **Laravel Policies**.

### Policies
Generate a policy for your model:
```bash
php artisan authz:policies --panel=admin
```
This generates a policy in `app/Policies` that uses permissions matching the resource.

## Protecting Pages

Add the `HasPageAuthz` trait to your custom Filament Pages:

```php
namespace App\Filament\Pages;

use AIArmada\FilamentAuthz\Concerns\HasPageAuthz;
use Filament\Pages\Page;

class CustomSettings extends Page
{
    use HasPageAuthz;
}
```
This will automatically check if the user has the permission (e.g., `custom-settings.view`) before allowing access.

## Protecting Widgets

Add the `HasWidgetAuthz` trait to your widgets:

```php
namespace App\Filament\Widgets;

use AIArmada\FilamentAuthz\Concerns\HasWidgetAuthz;
use Filament\Widgets\Widget;

class AnalyticsWidget extends Widget
{
    use HasWidgetAuthz;
}
```

## Custom Permissions

You can define custom permissions in `config/filament-authz.php`:

```php
'custom_permissions' => [
    'export-reports' => 'Export Reports',
    'view-debug-info',
],
```
These will appear in the "Custom Permissions" tab of the Role resource.

## Programmatic Checks

You can use the `Authz` facade or the standard Laravel `Gate`:

```php
use AIArmada\FilamentAuthz\Facades\Authz;

// Build a key
$key = Authz::buildPermissionKey('Order', 'delete'); // order.delete

// Check access
if (auth()->user()->can($key)) {
    // ...
}
```

## Super Admin

Assign the "Super Admin" role to a user to bypass all permission checks. The name of the role is configurable in the config file.
