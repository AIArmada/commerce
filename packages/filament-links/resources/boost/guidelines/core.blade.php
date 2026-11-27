@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
@verbatim
# Filament Links development

- This package is a Filament adapter. Use core Links actions for domain mutations and apply owner-scoped resource queries and server-side action authorization.
- Respect protected subject types for affiliate-managed links. Do not permit a generic form or table action to change protected attribution data.
- Generate public URLs through GenerateLinkUrl and leave signature enforcement and click writes to the core package.
@endverbatim
@endif
