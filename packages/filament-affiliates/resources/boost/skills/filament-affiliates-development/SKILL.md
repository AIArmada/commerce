---
name: filament-affiliates-development
description: Develop and integrate the modular Laravel Dune Filament Affiliates package, using owner-scoped feature APIs and canonical operations.
---

# Filament Affiliates development

Register `AIArmada\\FilamentAffiliates\\FilamentAffiliatesPlugin::make()` on the intended admin panel. The separate `AffiliatePanelProvider` configures the affiliate self-service portal; use the package configuration to enable or disable its capabilities.

- Feature modules contribute resources, pages, widgets and portal capabilities through the extension registry. Do not hardcode duplicate resource registration.
- Eloquent resource models are Filament adapter records; business mutations must go through the owning affiliate engine feature API, not through resource hooks that write records directly. Prefer scoped read projections for tables, selectors and reports.
- Use `AIArmada\\FilamentAffiliates\\Features\\Identity\\Api\\AffiliateAdminLinks::view($affiliateId)` for cross-package affiliate admin links. Do not import private resource classes from other packages.
- Authorize every action server-side, including bulk operations, and validate owner context before resolving records. Use engine APIs for payouts, program membership, support, fraud and conversion transitions.
- Preserve currency boundaries in reporting and settlement. Export sensitive data only after authorization.
- Resolve portal actors through `AIArmada\\Affiliates\\Features\\Identity\\Api\\PortalActorAffiliateReader`; never duplicate email matching or trust caller-supplied verification. Registration writes user, affiliate, and role atomically on a verified shared connection.

Verification from the commerce monorepo root:

```bash
composer --working-dir=packages/filament-affiliates arch:check
vendor/bin/phpstan analyse packages/filament-affiliates/src --level=6
vendor/bin/pest --parallel tests/src/FilamentAffiliates
```

Run Pint on changed PHP files and keep package architecture exceptions empty.
