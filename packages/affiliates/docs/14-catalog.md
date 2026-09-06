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
- `GET /api/affiliates/programs/{id}/catalog` — `v1` DTO: base rate,
  per-subject `effective` rate (deterministic product/category/program rules
  folded in), plus `variable_extras` (volume tiers, promotions) listed
  separately and never folded into the flat rate.

> [!WARNING]
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

Register a `PromotableProviderInterface` (tagged `affiliates.promotable`).
Without one, subjects fall back to active product/category rule `in` lists
with `/` placeholder URLs.

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

> [!WARNING]
> Snapshot uses `getApplicableRules()` + base math only. It never calls
> `CommissionRuleEngine::calculate()`, which would increment promotion usage.
