---
name: filament-commerce-support-development
description: Integrate and extend the AIArmada Filament Commerce Support adapter — the navigation manager, runtime overrides, and read-only reference-data resources — following Laravel Dune boundaries.
---

# Filament Commerce Support development

## Boundaries

- Adapter only. The package owns the navigation manager (`Pages\ManageCommerceNavigation`), the exchange-rate page (`Pages\ManageExchangeRates`), runtime navigation config (`Support\NavigationConfigurator`), and reference-data resources (`Resources\CurrencyResource`, `Resources\LanguageResource`, `Resources\TimezoneResource`).
- Domain state (reference data, exchange-rate settings) belongs to `commerce-support`. This package never defines domain models or calculations and never writes another package's tables.
- Every durable write goes through a single-writer Action in `Actions/`: `SaveNavigationSettings`, `SaveExchangeRates`, `SnapshotExchangeRates`. Pages validate at the boundary, then delegate to the Action.

## Navigation runtime

- `NavigationConfigurator::apply()` merges the persisted `CommerceNavigationSettings` over the captured file defaults. Call `NavigationConfigurator::restoreGenuineDefaults()` before re-applying saved settings so stale runtime config never accumulates.
- `NavigationConfigurator::isCaptured()` reports whether the file defaults were captured in the current process.
- Long-lived Octane workers refresh saved navigation per request by listening on `Laravel\Octane\Events\RequestReceived` and calling `NavigationConfigurator::refreshForRequest()`.

```php
use AIArmada\FilamentCommerceSupport\Support\NavigationConfigurator;

NavigationConfigurator::restoreGenuineDefaults();
NavigationConfigurator::apply();
```

## Reference data

- Currencies, languages, and timezones are reference data. The resources are read-only by default and enforce that server-side through `canCreate()`, `canEdit()`, `canDelete()`, and `canDeleteAny()` — disabling form inputs is not a security boundary.
- `shouldRegisterNavigation()` follows `filament-commerce-support.resources.<name>.enabled` independently of `filament-commerce-support.navigation.enabled`.
- Revalidate submitted IDs server-side; Filament form options are never authorization.

## Verification

```sh
composer --working-dir=packages/filament-commerce-support arch:check
./vendor/bin/pint --test packages/filament-commerce-support
./vendor/bin/pest tests/src/FilamentCommerceSupport
```
