---
title: Installation
---

# Installation

## Composer

Install the package via composer:

```bash
composer require aiarmada/filament-authz
```

## Register the Plugin

Add the `FilamentAuthzPlugin` to your Filament Panel provider:

```php
use AIArmada\FilamentAuthz\FilamentAuthzPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentAuthzPlugin::make(),
        ]);
}
```

## Set Up Your User Model

Ensure your `User` model uses the `HasRoles` trait from Spatie:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

## Configure Spatie Permission

Ensure you have run the Spatie Permission migrations:

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

## Publish Configuration (Optional)

You can publish the config file if you need to customize core behaviors:

```bash
php artisan vendor:publish --tag="filament-authz-config"
```

## Setup Command

Run the seeder command to create your first Super Admin role:

```bash
php artisan authz:super-admin
```
