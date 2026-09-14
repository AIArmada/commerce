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

- `standalone_resources`: Master switch for central resource pages (disabled by default). A resource registers only when this is `true` **and** its per-resource `enabled` flag is `true`.
- `relation_managers`: Advisory flag for hosts embedding the relation managers (enabled by default). Host resources may consult it in `getRelations()`; this package does not register relation managers itself.
- `imports`: Show the CSV import action on the contact method and social profile list pages (disabled by default). Every CSV parent reference is resolved through the core contacting owner guard.
- `exports`: Enable CSV/Excel export bulk actions.
- `verification_badges`: Show the verified/unverified badge column (in addition to `tables.show_verification_columns`).
- `open_url_actions`: Render social profile URLs as clickable links in tables and infolists. Only `http`/`https` URLs are linked.

The `ContactingFilamentConfig` navigation helpers and `ResolvesContactingModels`
are a host extension API: resources intentionally read `config()` directly (which
keeps runtime navigation overrides working), and hosts may use the config class or
the model resolver in their own panels and relation-manager hosts.

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

- `enabled`: Show this resource in the navigation (also requires `features.standalone_resources`).
- `read_only`: Disable edit/delete actions and remove the edit page. Standalone creates are never available; snapshots are always read-only.
