@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
## Filament Orders

@verbatim
- Use the Orders actions or service for lifecycle changes rather than direct financial writes.
- Revalidate tenant scope and actor authorization before actions and invoice downloads.
- Keep dashboard cache queries in `Persistence/`; use the actual `RefundStatus` enum for refunds.

```php
use AIArmada\Orders\Actions\AssignOrderInvoiceNumber;

$number = app(AssignOrderInvoiceNumber::class)->execute($order);
```
@endverbatim
@endif
