---
title: Filament Products Context
package: filament-products
status: current
surface: filament
family: catalog-and-identity
keywords:
  - filament
  - catalog-ui
  - variants
---

# Filament Products Context

## Snapshot
- Composer: `aiarmada/filament-products`
- Role: Filament catalog admin: products/categories/collections/attributes.
- Triggers: filament, catalog-ui, variants
- Search first: `src/Resources, src/Widgets, config, docs`
- Related: `products`, `filament-authz`
- Paired: `products` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../products/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `products`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `products` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Catalog admin UI.
- Skip when: Variant/attribute logic — see products.
- Owner/security: Owner revalidation everywhere.

## Key surfaces
- Resources: `AttributeGroupResource`, `AttributeResource`, `AttributeSetResource`, `BaseAttributeResource`, `BaseCatalogResource`, `BaseProductResource`, `CategoryResource`, `CollectionResource`, `ProductResource`
- Actions/Services: `Support/ProductStatsAggregator`
- Config `filament-products.php`: `navigation`, `group`, `resources`, `products`, `categories`, `collections`, `attributes`, `attribute_groups`, `attribute_sets`, `features`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
