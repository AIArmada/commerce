@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
## Filament Cashier CHIP

@verbatim
- This package is a Filament v5 adapter for Cashier CHIP billing. It does not own subscription, renewal or payment-method tables.
- Delegate mutations to the Cashier CHIP feature actions and use owner-scoped resources and widgets.
- Capture an explicit owner context when dispatching customer sync; never convert an unresolved tenant to global access.
- Do not expose recurring tokens or provider exceptions in the UI.

```php
use AIArmada\FilamentCashierChip\EntryPoints\Jobs\SyncCustomersToChipJob;
use AIArmada\CommerceSupport\Support\OwnerJobContextMapper;

SyncCustomersToChipJob::dispatch(OwnerJobContextMapper::fromModel($owner));
```
@endverbatim
@endif
