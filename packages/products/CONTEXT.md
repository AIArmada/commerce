---
title: Products Context
package: products
status: current
surface: domain
family: catalog-and-identity
keywords:
  - product
  - variant
  - category
  - collection
  - attribute
---

# Products Context

## Snapshot
- Composer: `aiarmada/products`
- Role: Catalog/PIM source of truth: products, variants, taxonomy, attributes.
- Triggers: product, variant, category, collection, attribute
- Search first: `src/Models, src/Actions, config, docs`
- Related: `filament-products`, `pricing`, `inventory`
- Paired: `filament-products` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-products/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-products`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Catalog modeling or variant generation.
- Skip when: Pricing — see pricing; stock — see inventory.
- Owner/security: Owner-scoped (all 10).

## Key surfaces
- Models: `Attribute`, `AttributeGroup`, `AttributeSet`, `AttributeValue`, `Category`, `Collection`, `Option`, `OptionValue`, `Product`, `Variant`
- Actions/Services: `Actions/ApplyAttributeChanges`, `Actions/CreateProduct`, `Actions/GenerateVariants`, `Actions/UpdateProduct`, `Actions/UpdateProductStatus`
- Config `products.php`: `database`, `table_prefix`, `json_column_type`, `tables`, `products`, `variants`, `options`, `option_values`, `variant_options`, `categories`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-models-reference.md`
