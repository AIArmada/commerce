---
title: Contacting Configuration
---

# Configuration

The package publishes a config file at `config/contacting.php`.

## Table Names

```php
'tables' => [
    'contact_methods' => 'contact_methods',
    'social_profiles' => 'social_profiles',
    'contact_snapshots' => 'contact_snapshots',
],
```

Override with environment variables:

```env
CONTACTING_TABLE_CONTACT_METHODS=custom_contact_methods
CONTACTING_TABLE_SOCIAL_PROFILES=custom_social_profiles
CONTACTING_TABLE_CONTACT_SNAPSHOTS=custom_snapshots
CONTACTING_TABLE_PREFIX=org_
```

## JSON Column Type

JSON column type is controlled by `commerce_json_column_type('contacting', 'json')` and inherits from `COMMERCE_JSON_COLUMN_TYPE` when set.

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
    'contact_snapshots' => true,
    'strict_social_platforms' => false,
    'strict_contact_types' => false,
],
```

- `contact_snapshots`: Enable/disable snapshot table (default: true)
- `owner.enabled`: Enable owner scoping for multi-tenancy
- `owner.include_global`: Include ownerless records in owner-scoped queries
- `owner.auto_assign_on_create`: Auto-assign current owner on creation
- `strict_social_platforms`: When true, only allow configured platforms
- `strict_contact_types`: When true, only allow configured contact types

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
        'youtube' => ['label' => 'YouTube', 'prefix' => 'www.youtube.com/@'],
        'linkedin' => ['label' => 'LinkedIn', 'prefix' => 'www.linkedin.com/in/'],
        'x' => ['label' => 'X / Twitter', 'prefix' => 'x.com/'],
        'threads' => ['label' => 'Threads', 'prefix' => 'www.threads.net/@'],
        'telegram' => ['label' => 'Telegram', 'prefix' => 't.me/'],
        'website' => ['label' => 'Website'],
        'other' => ['label' => 'Other'],
    ],
],
```

You can add more platforms by adding a `label`, and optionally a `prefix` or `suffix` for URL normalization. Platforms without a URL pattern are still valid for manual entry and display only.
