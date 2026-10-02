---
title: Program Catalog
---

# Program Catalog (network sync source)

Read-only snapshot consumed by `affiliate-network` over shared DB or HTTP.
See `ProgramCatalogService::snapshot()` for the implementation.

## Enable

```php
'catalog' => [
    'enabled' => env('AFFILIATES_CATALOG_ENABLED', true),
    'max_subjects' => env('AFFILIATES_CATALOG_MAX_SUBJECTS', 500),
],
```

## Endpoints

- `GET /api/affiliates/programs` — active + public programs only.
- `GET /api/affiliates/programs/{id}/catalog` — `v2` DTO: base rate,
  per-subject `effective` rate (deterministic product/category/program rules
  folded in), plus `variable_extras` (volume tiers, promotions) listed
  separately and never folded into the flat rate, and program creatives.

> **warning**
> The catalog routes live inside the existing API auth group (bearer token
> + `NeedsOwner` when `affiliates.owner.enabled`). Remote pulls therefore
> assume the merchant site runs with owner scoping **disabled** (the normal
> single-merchant case). If the merchant has owner mode on, the network
> caller must also present a resolvable owner context, otherwise it gets
> `400 Owner context required` — same as the existing affiliate endpoints.

## Imported-offer policy

- Imported offers land as `draft`: the operator reviews the mirrored rate,
  then publishes. Re-syncs update rates/landing/checksum/metadata but never
  touch `status`, `visibility`, or `slug`.
- `currency` resolves per subject → per program (`currency` column) →
  `affiliates.currency.default`.

## Contribute promotables

Register a `PromotableProviderInterface` with `PromotableRegistry`. There is no
container tag for this — the registry is the only registration path. Without a
provider, subjects fall back to active product/category rule `in` lists with
`/` placeholder URLs.

```php
use AIArmada\Affiliates\Contracts\PromotableProviderInterface;
use AIArmada\Affiliates\Support\Catalog\PromotableRegistry;

class ProductPromotables implements PromotableProviderInterface
{
    public function type(): string { return 'product'; }

    public function list(?string $programId = null): iterable
    {
        return [
            ['subject_key' => 'SKU-1', 'title' => 'Widget', 'url' => 'https://site/x/sku-1',
             'amount_minor' => 9900, 'context' => ['product_id' => 'SKU-1', 'category' => 'tools']],
        ];
    }
}

app(PromotableRegistry::class)->register(new ProductPromotables);
```

> **warning**
> Snapshot uses `getApplicableRules()` + base math only. It never calls
> `CommissionRuleEngine::calculate()`, which would increment promotion usage.

## Creative snapshot contract

Catalog version `v2` requires a `creatives` list, including `[]` when the
program has no creatives. Each entry contains `id`, `type`, `name`,
`description`, `asset_url`, `destination_url`, `width`, `height`,
`tracking_code`, and `metadata`. Optional values are explicitly null.
Assets use absolute public Media Library URLs; text links may have no file.
Programless creatives are excluded. Media is eager-loaded, and snapshots
never contain affiliate-personalized embed HTML.

Both local and HTTP catalog reads require an active, public program and
respect its owner boundary. The network mirrors each program creative
onto every imported subject offer. Merchant asset bytes remain on the
merchant's public disk; replacement/deletion takes effect on the next sync.

> **warning**
> Breaking contract: network readers require `v2` and the creative list.
> Upgrade producer and consumer together. There is no `v1` fallback,
> compatibility layer, or data backfill. Schema changes live in the original
> package migrations.
