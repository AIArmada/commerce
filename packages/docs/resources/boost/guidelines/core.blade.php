@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
## Docs

@verbatim
- Use `Data\DocData` for document creation and integer minor-unit values.
- Use `Contracts\DocumentPaymentRecorder`, `CreateDocVersion`, and the document service for changes. Do not mutate document versions or payment totals directly.
- Keep rendering and delivery tenant-scoped; public share and tracking endpoints must validate their tokens and prevent sensitive caching.
- Use the canonical `EntryPoints` namespaces for jobs and HTTP controllers.

```php
use AIArmada\Docs\Data\DocData;

$data = DocData::from(['doc_type' => 'invoice', 'items' => [['quantity' => 1, 'unit_price_minor' => 1000]]]);
```
@endverbatim
@endif
