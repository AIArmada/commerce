---
title: Troubleshooting
---

# Troubleshooting

## Installation

### Plugin not appearing

```php
FilamentAffiliateNetworkPlugin::make(),
```

Then clear caches:

```bash
php artisan filament:clear-cached-components
php artisan view:clear
php artisan config:clear
```

Confirm `aiarmada/affiliate-network` is installed.

### Missing icons

The plugin uses Filament v5 Heroicons; run `php artisan filament:install`.

## Authorization

### Surfaces return 403

Resources, the merchant dashboard, and both widgets gate on
`filament-affiliate-network.authorization.admin_ability` (default
`affiliate-network.admin`):

```php
Gate::define('affiliate-network.admin', fn (User $user): bool => $user->is_admin);
```

`MerchantStatementResource` returns `403` without it even for admins, because it
is a network-wide surface.

### Actions do nothing for a tenant user

Actions revalidate the record owner inside the core action. A tenant user acting
on another owner's record is rejected by `ScopesByBelongsToOwner` even when the
list filter showed it. This is expected; do not add `withoutOwnerScope()` to fix
it.

## Resources

### Empty tables

```php
AffiliateSite::query()->withoutGlobalScopes()->count();
app(\AIarmada\CommerceSupport\Contracts\OwnerResolverInterface::class)->resolve();
```

If the count is non-zero but the table is empty, owner scoping is filtering
rows. If the count is zero, check migrations/seeding.

### Offer site select is empty

The site select only lists **verified** sites (`status = verified` and
`verified_at` not null). Verify the site first. Editing an existing offer still
shows its current site even if unverified.

### "Enter a bare domain name…"

`AffiliateSiteForm` validates with `Platform\DomainHostValidator::isValidHostname()`:
no scheme, path, whitespace, IP address, or `localhost`/`.local`/`.internal`.
Enter `example.com`.

### Accidental rate lock (`source = manual`)

Editing any rate-block field flips `source` to `manual`, and later catalog syncs
hold the rates back (`locked`). Switch `source` back to `mirrored` to let the
next sync re-apply catalog rates.

## Relation managers

### Verify/approve actions missing

Visibility is state-driven: Verify/Reject only for `pending`, Suspend only for
`verified`, Reinstate only for `suspended`; Approve/Reject only for `pending`
applications, Revoke only for `approved`. Refresh the row.

### Cannot delete an imported creative

Imported creatives (`external_creative_id` not null) are catalog-owned. Only
`is_active`/`sort_order` are editable; `SyncSiteCreatives` reconciles deletions.
Manual rows can be deleted.

### `Adjust` hidden on a leg

Adjust requires `pending` or `posted` status and no `parent_leg_id`.
Companion legs and terminal legs (reversed/superseded) cannot be adjusted.

### `Reverse` hidden on a leg

Only `posted` legs reverse. A `pending` leg matures via **Lock matured**, and a
`provisional`/`superseded` leg never pays.

## Merchant statements

### Generate produces an empty statement

Only `posted` legs with no `statement_id` and no `parent_leg_id` attach. Confirm
the site/currency/period match posted legs. An empty statement is valid (leg
count 0).

### "Paid or void statements cannot be voided."

Void applies only to `draft`/`issued`. Paid is terminal.

### Mark Paid does not fulfil legs

Fulfillment only runs when the site has `require_settled_for_payout = true` and
the engine fulfills automatically. Otherwise the statement is marked paid and
payout happens out of band.

### Statement list looks empty for a merchant

`MerchantStatementResource` uses `withoutOwnerScope()` and is admin-only. It is
not a merchant surface; a merchant user will get `403`.

## Pages and widgets

### Merchant dashboard 404

```bash
php artisan route:list | grep merchant-dashboard
php artisan route:clear
```

> **info**
> There is no `/affiliate-network/marketplace` page in this package.

### Widgets missing

Widgets auto-register via the plugin. Verify the plugin is registered and
`NetworkAdminAccess::allows()` is true, or register them explicitly:

```php
$panel->widgets([
    \AIarmada\FilamentAffiliateNetwork\Widgets\NetworkStatsWidget::class,
    \AIarmada\FilamentAffiliateNetwork\Widgets\TopOffersWidget::class,
]);
```

### Widget data stale

Both widgets cache for 30 seconds via `OwnerCache::remember(null, ...)`. Clear
the cache between assertions or after mutations.

## Multi-tenancy

### Cross-tenant data visible

- `AFFILIATE_NETWORK_OWNER_ENABLED=true`.
- Align the Filament panel tenant with the owner resolver.
- Admin surfaces intentionally bypass scope; treat that as privileged, not
  tenant-visible.

### Custom resource leaks tenant data

Return owner-safe queries and revalidate IDs in action handlers:

```php
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use Illuminate\Database\Eloquent\Builder;

public static function getEloquentQuery(): Builder
{
    /** @var Builder<AffiliateOffer> $query */
    $query = parent::getEloquentQuery();

    return $query->with(['site', 'category']);
}
```

## Views

```bash
php artisan vendor:publish --tag=filament-affiliate-network-views
php artisan view:clear
```

The only shipped view is the merchant dashboard page.

## Debugging

- `tail -f storage/logs/laravel.log`
- Filament debug bar: `composer require --dev barryvdh/laravel-debugbar`
- Inspect the Livewire component in browser dev tools.

## Getting help

1. Check [core troubleshooting](../../affiliate-network/docs/99-troubleshooting.md).
2. Review Filament v5 docs.
3. Open an issue with PHP/Laravel/Filament versions, package versions, full
   error, and reproduction steps.
