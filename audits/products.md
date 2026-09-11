# Products Audit — DONE (2026-09-11)

## Verdict

`products` and `filament-products` have passed the implementation review. All
rated findings are implemented, falsified with evidence, or retained below as
explicit follow-ups. No migration is required. The canonical money and owner
contracts remain unchanged at their downstream boundaries.

## What was done

- **Money:** `products.defaults.store_money_in_cents` was removed. Product,
  variant, compare, and cost values are now minor-unit integers end to end;
  the one-off major-unit backfill for environments that ran with `false` is
  documented at `packages/products/docs/03-configuration.md:87`.
- **Owner enforcement:** the secure `true` default is aligned across config,
  model guards, policies, and writes. Seeder/console/queue work is owner
  contextualized. The ten owner-scoping contracts cover the catalog models;
  `packages/products/config/products.php:38-46` records the owner and variant
  defaults.
- **Identity:** application checks use the authoritative owner tuple. Product
  slugs are global, SKU identity is owner-scoped, and retry creation resolves
  an existing identical record through `createOrFirst`; a different owner or
  record remains a conflict. The regression coverage is in
  `tests/src/Products/GlobalUniquenessTest.php:27-70`.
- **Static typing:** the saving callback is narrowed to the consuming model's
  `self` type at `packages/products/src/Concerns/EnforcesOwnerUniqueIdentity.php:17-19`;
  this is a type-only narrowing for the runtime-safe `HasOwner` model set.
- **Taxonomy and contracts:** the generic `Visibility` enum and duplicate
  `IsAttributeEntity`/`IsOptionEntity` concerns were removed. The surviving
  `ProductVisibility`, `CatalogStatus`, `AttributeType`, and canonical integer
  price contracts remain in place.
- **Variants:** generation now caps at 200, chunks transactions, queues work
  above 50, and skips existing SKUs idempotently; the cap and queue branches
  are visible at `packages/products/src/Strategies/MatrixVariantGenerator.php:31-50`
  and the chunked writer at
  `packages/products/src/Strategies/MatrixVariantGenerator.php:87-119`.
  Filament surfaces the cap failure.
- **Boundaries and reuse:** `Collection` uses `OwnerQuery` (the implementation
  position is `packages/products/src/Models/Collection.php:460`), pricing and
  media helpers were extracted without API changes, and the policy preamble
  was centralized. The four missing model policies were added.
- **Filament adapter:** `hasConfigFile()` is registered at
  `packages/filament-products/src/FilamentProductsServiceProvider.php:25`;
  owner-aware statistics use `OwnerCache`, and the optional customer selector
  remains guarded and documented.

## Audit deviations

- The audit's “unused Customer import” claim was falsified by the guarded live
  selector at
  `packages/filament-products/src/Resources/ProductResource/Schemas/ProductForm.php:226-274`;
  the optional integration was retained.
- The suspected `TopSellingProductsWidget` orders/inventory N+1 was falsified
  by source inspection; no such per-product count path was present.
- The audit's package-local “zero tests” observation is superseded by the
  repo Area coverage and the ten owner contract tests; the package-local test
  directory remains intentionally absent.

## Residual notes

- No migration was added. **Uniques on `(owner_scope, slug/sku/code)` are
  wrong-key but changing them to tuple-keyed partial uniques requires dedup
  cleanup and is explicitly deferred — no migration now.**
- **If `store_money_in_cents=false` was ever enabled in an environment, that
  environment needs a one-off major→minor backfill (`price*100`) before
  locking the flag — call out in release notes, do not automate here.**
- **Pivot PK inconsistency (composite vs surrogate `uuid id`) is documented as
  a style exception, not a migration.**
- `parent_scope` remains inert for existing rows; identity is scoped by the
  owner tuple and `parent_id`, as documented at
  `packages/products/docs/05-models-reference.md:129-135`.
- **`attr_val_unique(attribute_id, attributable_type, attributable_id,
  locale)` with nullable `locale` (NULL-unsafe: two NULL-locale values for
  same attribute+model pass the unique — add `COALESCE(locale,'')` partial
  index or require locale non-null in writers; code fix now, index later).**
- **`whereHas('attributeValues')` scopes in
  `HasAttributes::scopeWhereCustomAttribute(s)` will not use indexes at scale
  — acceptable for admin filtering, but do not use EAV predicates on storefront
  hot paths (add materialized columns or dedicated indexes if storefront
  search needs them).**
- Central `AttributeCaster` remains a low follow-up: `AttributeValue.value` is
  still raw text and readers retain their existing casts.
- The repeated Filament media form schema remains a low follow-up: extract
  `Support/ProductMediaForm.php::gallerySchema()/heroSchema()` if the
  duplication becomes costly.
- `PricesRelationManager` continues to reach into pricing models for admin UX;
  its optional-package gating remains a follow-up for a standalone catalog
  install.
- `database.table_prefix` remains a documented runtime fallback because
  migrations use explicit table names; it is not deleted.

## Verification

- `php -d memory_limit=1G ./vendor/bin/phpstan analyse packages/products/src --level=6` — **No errors**.
- Products: **589 passed, 1064 assertions** (`tests/src/Products`).
- FilamentProducts: **25 passed, 98 assertions** (`tests/src/FilamentProducts`).

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
