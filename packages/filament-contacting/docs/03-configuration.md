---
title: Filament Contacting Configuration
---

# Configuration

The package publishes a config file at `config/filament-contacting.php`.

## Navigation

```php
'navigation' => [
    'group' => 'Contacting',
    'sort' => 70,
    'icons' => [
        'contact_methods' => 'heroicon-o-phone',
        'social_profiles' => 'heroicon-o-share',
        'contact_snapshots' => 'heroicon-o-archive-box',
    ],
],
```

- `group`: Navigation group label for all contacting resources.
- `sort`: Sort order within the navigation group.
- `icons`: Icon overrides per resource type.

## Tables

```php
'tables' => [
    'default_pagination' => 25,
    'show_owner_columns' => false,
    'show_verification_columns' => true,
    'show_visibility_columns' => true,
],
```

- `default_pagination`: Default rows per page.
- `show_owner_columns`: Show `owner_type`/`owner_id` columns (enable for global admin panels).
- `show_verification_columns`: Show verification badge columns.
- `show_visibility_columns`: Show public/private badge columns.

## Features

```php
'features' => [
    'standalone_resources' => false,
    'relation_managers' => true,
    'imports' => false,
    'exports' => true,
    'verification_badges' => true,
    'open_url_actions' => true,
],
```

- `standalone_resources`: Enable central resource pages (disabled by default).
- `relation_managers`: Enable relation managers for embedding in other resources.
- `imports`: Enable CSV/Excel import actions (disabled by default — requires owner-safe resolver).
- `exports`: Enable CSV/Excel export actions.
- `snapshots`: Enable snapshot viewer (read-only).
- `verification_badges`: Show verified/unverified badges in tables.
- `open_url_actions`: Show "Open URL" actions for website/social links.

## Resources

```php
'resources' => [
    'contact_methods' => [
        'enabled' => false,
        'read_only' => false,
    ],
    'social_profiles' => [
        'enabled' => false,
        'read_only' => false,
    ],
    'contact_snapshots' => [
        'enabled' => false,
        'read_only' => true,
    ],
],
```

- `enabled`: Show this resource in the navigation.
- `read_only`: Hide create/edit/delete actions. Snapshots are read-only by default.
