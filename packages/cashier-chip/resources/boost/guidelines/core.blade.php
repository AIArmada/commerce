@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
## Cashier CHIP

@verbatim
- Subscription renewal claims, settlement and reconciliation belong to the Subscriptions feature. Preserve owner context and the frozen renewal amount.
- Use concrete Actions rather than writing subscription or renewal state from entry points.
- Customer sync runs in Cashier CHIP, not the Filament adapter. An unresolved owner must not become an implicit global job.
- Do not retry an ambiguous remote charge using a new idempotency key.

```php
use AIArmada\CashierChip\Features\Subscriptions\Actions\RenewSubscriptions;

$summary = app(RenewSubscriptions::class)->handle(dryRun: true, graceHours: 0);
```
@endverbatim
@endif
