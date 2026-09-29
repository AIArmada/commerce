---
title: Contacting Configuration
---

# Configuration

The package publishes a config file at `config/contacting.php`.

## Table Names

```php
'database' => [
    'table_prefix' => env('CONTACTING_TABLE_PREFIX', ''),
    'json_column_type' => env('CONTACTING_JSON_COLUMN_TYPE', 'json'),
    'tables' => [
        'contact_methods' => env('CONTACTING_TABLE_CONTACT_METHODS', $tablePrefix . 'contact_methods'),
        'social_profiles' => env('CONTACTING_TABLE_SOCIAL_PROFILES', $tablePrefix . 'social_profiles'),
        'contact_snapshots' => env('CONTACTING_TABLE_CONTACT_SNAPSHOTS', $tablePrefix . 'contact_snapshots'),
    ],
],
```

All three keys sit under `contacting.database`. `table_prefix` is the shared
fallback for the three table names; each table can be overridden individually.

Override with environment variables:

```env
CONTACTING_TABLE_CONTACT_METHODS=custom_contact_methods
CONTACTING_TABLE_SOCIAL_PROFILES=custom_social_profiles
CONTACTING_TABLE_CONTACT_SNAPSHOTS=custom_snapshots
CONTACTING_TABLE_PREFIX=org_
```

## JSON Column Type

The shipped `contacting.database.json_column_type` defaults to `json`. Migrations
resolve the column type through `commerce_json_column_type('contacting', 'json')`,
which prefers `CONTACTING_JSON_COLUMN_TYPE`, then `COMMERCE_JSON_COLUMN_TYPE`,
then the config value.

## Defaults

```php
'defaults' => [
    'country_code' => env('CONTACTING_DEFAULT_COUNTRY_CODE', 'MY'),
    'public_by_default' => true,
    'verified_by_default' => false,
],
```

## Features

```php
'features' => [
    'owner' => [
        'enabled' => env('CONTACTING_OWNER_ENABLED', true),
        'include_global' => env('CONTACTING_OWNER_INCLUDE_GLOBAL', false),
        'auto_assign_on_create' => env('CONTACTING_OWNER_AUTO_ASSIGN', true),
    ],
    'contact_snapshots' => env('CONTACTING_SNAPSHOTS_ENABLED', true),
    'strict_social_platforms' => false,
    'strict_contact_types' => false,
],
```

- `contact_snapshots`: Enable/disable snapshot creation (default: true). When
  disabled, snapshot actions throw `ContactSnapshotsDisabledException`; they do
  not return an unsaved placeholder.
- `owner.enabled`: Enable owner scoping for multi-tenancy
- `owner.include_global`: Include ownerless records in owner-scoped queries
- `owner.auto_assign_on_create`: Auto-assign current owner on creation
- `strict_social_platforms`: When true, only allow configured platforms
- `strict_contact_types`: When true, only allow configured contact types

Contact methods default to private for `email`, `phone`, `mobile`, `whatsapp`,
and `fax`, even when `defaults.public_by_default` is true. Website and other
non-PII contact types use `public_by_default`. Social profiles use
`public_by_default`. Set `is_public` explicitly when a different policy is
required.

## Contact Method Types

```php
'contact_methods' => [
    'types' => ['email', 'phone', 'mobile', 'whatsapp', 'website', 'telegram', 'fax', 'other'],
    'purposes' => ['general', 'admin', 'support', 'billing', 'sales', 'registration', 'media', 'donation', 'partnership', 'emergency', 'privacy', 'other'],
],
```

## Social Platforms

```php
'social_profiles' => [
    'platforms' => [
        'facebook' => ['label' => 'Facebook', 'prefix' => 'www.facebook.com/'],
        'instagram' => ['label' => 'Instagram', 'prefix' => 'www.instagram.com/'],
        'tiktok' => ['label' => 'TikTok', 'prefix' => 'www.tiktok.com/@'],
        'youtube' => ['label' => 'YouTube', 'prefix' => 'www.youtube.com/'],
        'linkedin' => ['label' => 'LinkedIn', 'prefix' => 'www.linkedin.com/in/'],
        'x' => ['label' => 'X / Twitter', 'prefix' => 'x.com/'],
        'threads' => ['label' => 'Threads', 'prefix' => 'www.threads.net/@'],
        'telegram' => ['label' => 'Telegram', 'prefix' => 't.me/'],
        'website' => ['label' => 'Website'],
        'other' => ['label' => 'Other'],
    ],
],
```

The snippet above is trimmed; the published config ships a fuller platform list. You can add more platforms by adding a `label`, and optionally a `prefix` or `suffix` for URL normalization. Platforms without a URL pattern are still valid for manual entry and display only.
