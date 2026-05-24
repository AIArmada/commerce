---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Filament 5.6+
- aiarmada/customers package

## Composer Installation

Install the plugin via Composer:

```bash
composer require aiarmada/filament-customers
```

The package service provider auto-registers via Laravel package discovery, but you still need to register the Filament plugin in each panel that should expose the resources and widgets.

## Register Plugin

Add the plugin to your Filament panel:

```php
use AIArmada\FilamentCustomers\FilamentCustomersPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->path('admin')
        ->plugins([
            FilamentCustomersPlugin::make(),
        ]);
}
```

## Dependencies

Ensure you have the core customers package installed:

```bash
composer require aiarmada/customers
```

Run migrations if you haven't already:

```bash
php artisan migrate
```

## Verification

Visit your Filament admin panel. You should see:
- **Customers** resource in the CRM navigation group
- **Segments** resource in the CRM navigation group
- **Customer Stats** widget on the dashboard (if enabled)
- **Recent Customers** widget on the dashboard (if enabled)

## Configuration surface

`filament-customers` does not publish its own config file. Configuration happens through:

- panel plugin registration with `FilamentCustomersPlugin::make()`,
- extending or replacing the package resources/widgets in your application,
- core package settings in `config/customers.php`.

## Default Configuration

The plugin works out of the box with sensible defaults:

- Navigation icon: `heroicon-o-users` (Customers), `heroicon-o-user-group` (Segments)
- Navigation group: "CRM"
- Navigation sort: Customers (1), Segments (2)
- Owner scoping: Automatically applied if enabled in config
- Widgets: Enabled on dashboard

## Customization Options

You can customize the plugin by extending resources:

```php
use AIArmada\FilamentCustomers\Resources\CustomerResource;

class CustomCustomerResource extends CustomerResource
{
    protected static ?string $navigationGroup = 'Sales';
    
    protected static ?int $navigationSort = 10;
}
```

Then register your custom resource:

```php
$panel->resources([
    CustomCustomerResource::class,
]);
```

## Policies

The plugin uses Laravel policies for authorization. Ensure your User model can authorize:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    // Your user model
}
```

Policies are automatically registered:
- `CustomerPolicy` - Customer authorization
- `SegmentPolicy` - Segment authorization
- `AddressPolicy` - Address authorization
- `CustomerNotePolicy` - Note authorization

## Multi-Tenancy Setup

If using multi-tenancy, ensure your application resolves owner context before these resources run. This package consumes the shared owner context; it does not resolve tenancy itself.

```php
// Example: use your app's owner-identification middleware / Filament tenancy integration
// so OwnerContext is resolved before the resource queries run.
```

## Testing

Create a test customer to verify installation:

```php
use AIArmada\Customers\Models\Customer;

Customer::create([
    'first_name' => 'Test',
    'last_name' => 'Customer',
    'email' => 'test@example.com',
    'status' => 'active',
]);
```

Then visit the Customers resource in your Filament panel.

## Troubleshooting

### Customers Not Appearing

**Problem**: Customer list is empty even though customers exist in database.

**Solution**: Check owner scoping:

```php
// Verify owner context is set
$owner = OwnerContext::resolve();
dd($owner); // Should not be null in multi-tenant mode
```

### Navigation Not Showing

**Problem**: CRM navigation group not appearing.

**Solution**: Ensure plugin is registered in panel:

```php
// app/Providers/Filament/AdminPanelProvider.php
return $panel->plugins([
    FilamentCustomersPlugin::make(),
]);
```

### Permission Denied

**Problem**: Cannot view or edit customers.

**Solution**: Implement policy or allow all for testing:

```php
// app/Policies/CustomerPolicy.php
public function viewAny(User $user): bool
{
    return true; // For testing only
}
```

## Next Steps

- [Configuration](03-configuration.md) - Understand the package configuration surface
- [Usage](04-usage.md) - Learn about resources and common admin flows
- [Widgets](05-widgets.md) - Review dashboard widgets
- [Troubleshooting](99-troubleshooting.md) - Debug common issues
