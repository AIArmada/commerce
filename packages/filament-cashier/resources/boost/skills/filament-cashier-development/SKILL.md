---
name: filament-cashier-development
description: Develop integrations with filament-cashier using its Dune package boundaries.
---

# filament-cashier development

Filament Cashier is a presentation adapter for Cashier. Use core Cashier actions and queries for billing; never implement payment, subscription or tenant ownership rules in UI components. Authorize every submitted record and verify its owner scope on the server. Customer subscription reads use AIArmada\Cashier\Queries\CustomerSubscriptionsQuery.

Read the package documentation and tests for current APIs. Verify changed behavior using the monorepo Pest suite and the package architecture checks.
