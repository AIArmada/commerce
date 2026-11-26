@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
## Filament Affiliates

Register `AIArmada\\FilamentAffiliates\\FilamentAffiliatesPlugin::make()` on the intended admin panel. The separate `AffiliatePanelProvider` configures the affiliate self-service portal; use the package configuration to enable or disable its capabilities.

- Feature modules contribute resources, pages, widgets and portal capabilities through the extension registry. Do not hardcode duplicate resource registration.
- Eloquent resource models are Filament adapter records; business mutations must go through the owning affiliate engine feature API, not through resource hooks that write records directly. Prefer scoped read projections for tables, selectors and reports.
- Use `AIArmada\\FilamentAffiliates\\Features\\Identity\\Api\\AffiliateAdminLinks::view($affiliateId)` for cross-package affiliate admin links. Do not import private resource classes from other packages.
- Authorize every action server-side, including bulk operations, and validate owner context before resolving records. Use engine APIs for payouts, program membership, support, fraud and conversion transitions.
- Preserve currency boundaries in reporting and settlement. Export sensitive data only after authorization.
@endif
