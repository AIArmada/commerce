@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
## Filament Affiliate Network

Register `AIArmada\\FilamentAffiliateNetwork\\FilamentAffiliateNetworkPlugin::make()` on the intended merchant/admin panel. The adapter is organized into Catalog, Dashboard, Enrollment, Ledger, Postbacks and Sites features. Feature modules register panel contributions through the package extension registry.

- Use the network package's owning actions and feature APIs for domain operations. Filament records are UI adapters; do not implement ledger, enrollment, site verification or postback writes inside table callbacks.
- Keep catalog, enrollment and ledger reads owner scoped; do not expose the network engine's unscoped system APIs to arbitrary panel users. Revalidate IDs and permissions at execution time, not just when displaying buttons.
- Keep money in minor units with its original ISO currency. Avoid duplicate conversions, direct balance adjustments and bypassing the network's idempotency and payout rules.
- Route external HTTP, DNS and webhook work through the network engine's driver contracts, not through UI code. Do not import private features from sibling adapters.
@endif
