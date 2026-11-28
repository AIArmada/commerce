---
name: filament-pricing-development
description: Build owner-scoped Filament pricing administration using domain actions.
---

# Filament pricing

Delegate mutations to `AIArmada\Pricing\Actions`. Resolve settings through `PricingSettings` and save through `UpdatePricingSettings` after verifying the configured global settings ability. Keep resource queries owner-scoped; do not bypass authorization or write data during page hydration. Verify package architecture and affected Pest tests.
