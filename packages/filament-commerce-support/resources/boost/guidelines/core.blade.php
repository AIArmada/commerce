@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
## Filament Commerce Support

The package is a Filament v5 adapter that owns the navigation manager UI and read-only reference-data browsing (currencies, languages, timezones). Domain state and its writes stay in `commerce-support`; this package only adapts them to Filament.

- Register `AIArmada\FilamentCommerceSupport\FilamentCommerceSupportPlugin` on the panel to mount the pages and reference resources.
- Persisted navigation lives in `CommerceNavigationSettings`. Rebuild runtime config by restoring genuine file defaults before re-applying saved settings, so stale overrides never accumulate.
- Long-lived Octane workers refresh saved navigation on `Laravel\Octane\Events\RequestReceived`; do not hand-cache navigation config across requests.
- Reference-data resources are read-only by default. Flip `filament-commerce-support.resources.<name>.read_only` to allow writes, and re-check every submitted ID server-side.
- Route all durable writes through the package's `Actions/` classes (`SaveNavigationSettings`, `SaveExchangeRates`, `SnapshotExchangeRates`).

@verbatim
<code-snippet name="Rebuild navigation after a settings change" lang="php">
use AIArmada\FilamentCommerceSupport\Support\NavigationConfigurator;

NavigationConfigurator::restoreGenuineDefaults();
NavigationConfigurator::apply();
</code-snippet>
@endverbatim
@endif
