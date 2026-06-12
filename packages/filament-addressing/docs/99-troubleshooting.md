---
title: Filament Addressing Troubleshooting
---

## Resources Do Not Appear

Check that the plugin is registered in your Filament panel provider.

```php
FilamentAddressingPlugin::make()
```

Check config:

```php
'resources' => [
    'countries' => [
        'enabled' => true,
    ],
],
```

Clear config cache if needed:

```bash
php artisan optimize:clear
```

## Country List Is Empty

The Filament package does not seed countries.

Run the core addressing command:

```bash
php artisan address:seed-countries
```

## Area Import Fails With Missing Country

Seed countries first.

```bash
php artisan address:seed-countries
```

Your CSV must use ISO2 country code:

```csv
country_code
MY
```

Do not use full country name as the key.

## Parent Area Does Not Resolve

Check that `parent_source_id` references a row from the same source.

Example:

```csv
source,source_id,parent_source_id
app.malaysia,MY-10,
app.malaysia,MY-10-PETALING,MY-10
```

## Address Resource Shows Too Much Data

Disable the central address resource:

```php
'addresses' => [
    'enabled' => false,
],
```

Actual addresses are usually better managed inside the owning package resource using `AddressesRelationManager`.

## Snapshot Cannot Be Edited

That is intentional.

Snapshots preserve historical address state. Edit the owning domain record or create a new snapshot through core domain workflows.

## Provider Payload Not Visible

Enable it only for debugging/admin panels:

```php
'features' => [
    'show_provider_payload' => true,
],
```

## Verification Commands

```bash
./vendor/bin/pest --parallel packages/filament-addressing/tests
./vendor/bin/phpstan analyse packages/filament-addressing/src --level=6
./vendor/bin/pint packages/filament-addressing/src packages/filament-addressing/tests
rg -n -- "constrained\(|cascadeOnDelete\(" packages/filament-addressing
rg -n -- "SoftDeletes" packages/filament-addressing
```
