@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
## Addressing

@verbatim
- Resolve formatting, validation, normalization, navigation links and lookup through the contracts in `AIArmada\Addressing\Contracts`.
- Keep geography providers declarative: return CountryStateSeedData and area metadata; do not write database rows from a provider.
- Use CountryGeographySeeder and AddressingDatasetSeeder to orchestrate the reference imports.
- Use AddressWriter, AddressAreaWriter, PostalCodeWriter and AddressSnapshotWriter for mutations; they return persisted IDs. Pass null as AddressWriter's assignment map to preserve existing compatible assignments, or an explicit map to replace them.
- Attach, update, detach and select primary addresses through AddressAttachmentWriter. Pass persisted identifiers; revalidate owner scope on every mutation.
- AddressAreaSearch returns a list of immutable AddressAreaMatchData values, not Eloquent models.
- Report unresolved values through ResolutionGapReporter::report(), which returns a persisted gap ID. Use ResolutionGapCuration to match or ignore that ID; do not write gap records from adapters.
- Keep external HTTP requests in Drivers. Do not call Google or OneMap directly from Filament components.
- Use immutable AddressData for formatting, validation and navigation. Do not duplicate the normalization rules in consuming packages.

```php
use AIArmada\Addressing\Contracts\AddressNormalizer;
use AIArmada\Addressing\Contracts\AddressFormatter;
use AIArmada\Addressing\Contracts\AddressValidator;
use AIArmada\Addressing\Contracts\AddressNavigationLinks;

$address = app(AddressNormalizer::class)->normalize($input);
$errors = app(AddressValidator::class)->validate($address);
$formatted = app(AddressFormatter::class)->format($address);
$links = app(AddressNavigationLinks::class)->build($address);
```
@endverbatim
@endif
