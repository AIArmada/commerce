# Filament Permissions

A comprehensive Filament v4 permissions suite powered by Spatie laravel-permission with multi-guard support, panel-aware gating, and rich admin UX.

## Features

- 🔐 **Multi-Guard Support**: Multiple authentication guards with configurable defaults
- 🎛️ **Panel-Aware**: Per-panel guard mapping and middleware injection
- 👥 **Complete CRUD**: Role, Permission, and User management resources
- 🔗 **Relation Managers**: Attach/detach permissions to roles, roles/permissions to users
- 🎨 **Macros**: `requiresPermission()` and `requiresRole()` for Actions, Navigation, Widgets, Columns, Filters
- 🚀 **Super Admin Bypass**: Automatic `Gate::before` for unrestricted access
- 📊 **Permission Explorer**: Grouped permission viewer with role assignments
- 📈 **Diff Widget**: Dashboard widget showing unused permissions and role statistics
- 🎭 **Impersonation Banner**: Visual indicator for super-admin context
- 🔄 **Sync Commands**: Import/export JSON, sync from config, doctor command for diagnostics
- 🧪 **Comprehensive Tests**: Pest test suite covering all features

## Installation

```bash
composer require aiarmada/filament-permissions
```

### Publish Configuration

```bash
php artisan vendor:publish --tag=filament-permissions-config
```

### Run Spatie Permission Migrations

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

### Add HasRoles Trait to User Model

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

### Register Plugin in Panel Provider

```php
use AIArmada\FilamentPermissions\Plugin\FilamentPermissionsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentPermissionsPlugin::make(),
        ]);
}
```

## Configuration

Edit `config/filament-permissions.php`:

```php
return [
    'guards' => ['web', 'admin'], // Multiple guards support
    'panel_guard_map' => [
        'admin-panel' => 'admin',
        'staff-panel' => 'web',
    ],
    'super_admin_role' => 'Super Admin',
    'enable_user_resource' => true,
    'navigation' => [
        'group' => 'Access Control',
        'sort' => 90,
    ],
    'features' => [
        'doctor' => true,
        'policy_generator' => true,
        'impersonation_banner' => true,
        'permission_explorer' => true,
        'diff_widget' => true,
        'export_import' => true,
    ],
];
```

## Usage

### Macros

```php
// Actions
Action::make('export')
    ->requiresPermission('orders.export');

// Navigation Items
NavigationItem::make('Reports')
    ->requiresRole(['Admin', 'Analyst']);

// Table Columns
TextColumn::make('internal_notes')
    ->requiresPermission('orders.view_internal');

// Filters
Filter::make('high_value')
    ->requiresPermission('orders.filter_high_value');
```

### Commands

```bash
# Sync roles & permissions from config
php artisan permissions:sync --flush-cache

# Diagnose issues
php artisan permissions:doctor

# Export to JSON
php artisan permissions:export storage/permissions.json

# Import from JSON
php artisan permissions:import storage/permissions.json --flush-cache

# Generate policy stubs
php artisan permissions:generate-policies
```

## Testing

```bash
cd packages/commerce/packages/filament-permissions
vendor/bin/pest
```

## License

MIT License. See LICENSE for details.
