@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
## Orders

@verbatim
- Use Orders contracts for payments, refunds, draft editing, and document issuance; do not write payment or refund totals from integrations.
- Preserve tenant scope and use the persisted order identity when locking or issuing documents.
- Payment and refund amounts are integer minor units. Never replace completed payment evidence with the order total.
- Use `Contracts\OrderInvoiceNumber` for persistent invoice identity; never mint a new number on every download.
- Use `Runtime\OrderInvoiceDownload` for authorized invoice responses without importing feature internals or the PDF SDK.

```php
use AIArmada\Orders\Contracts\OrderInvoiceNumber;

$number = app(OrderInvoiceNumber::class)->assign($orderId);
```
@endverbatim
@endif
