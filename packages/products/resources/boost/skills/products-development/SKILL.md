---
name: products-development
description: Integrate the Products catalog, owner-scoped write actions, variants and composite snapshots.
---

# Products integration

- Use `AIArmada\Products\Actions\CreateProduct`, `UpdateProduct`, `UpdateProductStatus` and `EnsureProductBySlug` for catalog writes. Resolve the owner first; explicit global context is required for global writes when owner scoping is enabled.
- Use `SaveVariant`, `SaveOption` and `ReplaceOptionValues` for variant and option changes. Use `SaveCategory` for category hierarchy edits.
- Use `CreateCompositeProduct`, `ReplaceProductComponents`, and `PublishCompositeProduct` for complete composite lifecycle operations. Never activate incomplete packages or bypass the owning actions.
- Read composite snapshots and availability through the product or variant buyable APIs. Use the flattened component identities and quantities for downstream fulfillment; never treat the parent as inventory.
- Store monetary amounts in integer minor units and use the Pricing package for price-list writes.
- Run package architecture checks and the affected Products, Filament, Checkout and Inventory regression tests after integration changes.
