@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
## Filament Docs

@verbatim
- Use the Docs service and named actions for payment recording and document updates.
- Use the canonical `Data\DocData` and `Data\ShareLinkData` DTOs.
- Dispatch bulk PDF generation through `EntryPoints\Jobs\GenerateDocPdfsJob` and revalidate owner scope before rendering.

```php
use AIArmada\Docs\Data\DocData;

$data = DocData::from(['doc_type' => 'invoice']);
```
@endverbatim
@endif
