---
name: pricing-development
description: Develop integrations using AIArmada Pricing's immutable results, transactional write actions and owner-scoped price resolution.
---

# Pricing integration

- Resolve `AIArmada\Pricing\Contracts\PriceCalculatorInterface` through the container and call `calculate()` or `calculateMany()` with priceable items, positive quantities and authorized context.
- Results are immutable `PriceResultData` and `TierPriceResultData`; presentation formatting lives in `AIArmada\Pricing\Support\PriceResultFormatter`.
- Price lists, individual prices and tiers are owned by Pricing. Call the named actions in `AIArmada\Pricing\Actions` instead of directly mutating these models in sibling packages.
- Resolve the tenant before quoting or writing when owner scoping is enabled. Do not use an arbitrary customer or price-list ID from a request without validating its access.
- Prices are stored as integer minor units with an explicit currency. Respect effective-date windows and inactive records.
- Run the package architecture checks and affected pricing, checkout and Filament regression tests after changes.

- `AIArmada\Pricing\Actions\CopyPriceablePrices` copies price-list entries and quantity tiers between distinct persisted priceable records in the authorized owner scope.
