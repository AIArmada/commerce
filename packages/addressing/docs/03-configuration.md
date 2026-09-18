---
title: Configuration
---

# Configuration

The package publishes a `config/addressing.php` file with these sections:

## Database

```php
'database' => [
    'json_column_type' => 'jsonb',
    'tables' => [
        'countries' => 'countries',
        'areas' => 'address_areas',
        'addresses' => 'addresses',
        'addressables' => 'addressables',
        'snapshots' => 'address_snapshots',
        'states' => 'states',
        'cities' => 'cities',
    ],
],
```

`addressing.database.tables.*` is the only table-name configuration source.
Runtime models, integrations, and migrations resolve names through
`AddressingTableResolver`; configure this map before deploying.

JSON column type is controlled by `addressing.database.json_column_type` and
inherits from the package/shared default when set.

## Tables

```php
'database' => [
    'tables' => [
        'countries' => 'countries',
        'areas' => 'address_areas',
        'addresses' => 'addresses',
        'addressables' => 'addressables',
        'snapshots' => 'address_snapshots',
        'states' => 'states',
        'cities' => 'cities',
        'area_state_links' => 'address_area_state_links',
        'area_names' => 'address_area_names',
        'area_roles' => 'address_area_roles',
        'area_relationships' => 'address_area_relationships',
        'postal_codes' => 'postal_codes',
        'area_postal_codes' => 'address_area_postal_codes',
        'address_area_assignments' => 'address_area_assignments',
    ],
],
```

Override any table name via environment variables or config publishing.

- `states` and `cities` back the first-class `State` and `City` models
- `cities.state_id` is nullable; countries without a state/province level can still use country-scoped cities
- Addresses may store free-text `state` / `city` strings and optionally link via `state_id` / `city_id`

## Owner scoping

Instance address data is owner-scoped by default. Existing ownerless rows are
not supported by the owner cutover. The owner-column migration fails closed if
any pre-existing address, addressable, or snapshot row has a null owner tuple;
this release does not backfill or retain a legacy compatibility path.

Intentional global rows remain possible only when the application explicitly
uses `OwnerContext::withOwner(null, ...)`. They are not included in tenant
queries unless the caller explicitly uses global context or opts into
`include_global`.

```php
'features' => [
    'owner' => [
        'enabled' => true,
        'include_global' => false,
        'auto_assign_on_create' => true,
    ],
],
```

Use `OwnerContext::withOwner($owner, ...)` for tenant work and
`OwnerContext::withOwner(null, ...)` for deliberate global work. Reference
geography tables remain global and are not owner-scoped.

## Models and Geography Providers

```php
'models' => [
    'country' => AIArmada\Addressing\Models\AddressCountry::class,
    'state' => AIArmada\Addressing\Models\State::class,
    'city' => AIArmada\Addressing\Models\City::class,
    'area' => AIArmada\Addressing\Models\AddressArea::class,
    'address' => AIArmada\Addressing\Models\Address::class,
    'snapshot' => AIArmada\Addressing\Models\AddressSnapshot::class,
],
'geography' => [
    'providers' => [
        AIArmada\Addressing\Geography\Malaysia\MalaysiaGeographyProvider::class,
        AIArmada\Addressing\Geography\Singapore\SingaporeGeographyProvider::class,
        AIArmada\Addressing\Geography\Indonesia\IndonesiaGeographyProvider::class,
        AIArmada\Addressing\Geography\Brunei\BruneiGeographyProvider::class,
        AIArmada\Addressing\Geography\Bahrain\BahrainGeographyProvider::class,
        AIArmada\Addressing\Geography\Bangladesh\BangladeshGeographyProvider::class,
        AIArmada\Addressing\Geography\Egypt\EgyptGeographyProvider::class,
        AIArmada\Addressing\Geography\India\IndiaGeographyProvider::class,
        AIArmada\Addressing\Geography\Jordan\JordanGeographyProvider::class,
        AIArmada\Addressing\Geography\Kuwait\KuwaitGeographyProvider::class,
        AIArmada\Addressing\Geography\Morocco\MoroccoGeographyProvider::class,
        AIArmada\Addressing\Geography\Oman\OmanGeographyProvider::class,
        AIArmada\Addressing\Geography\Pakistan\PakistanGeographyProvider::class,
        AIArmada\Addressing\Geography\Qatar\QatarGeographyProvider::class,
        AIArmada\Addressing\Geography\SaudiArabia\SaudiArabiaGeographyProvider::class,
        AIArmada\Addressing\Geography\SouthAfrica\SouthAfricaGeographyProvider::class,
        AIArmada\Addressing\Geography\Turkiye\TurkiyeGeographyProvider::class,
        AIArmada\Addressing\Geography\UnitedArabEmirates\UnitedArabEmiratesGeographyProvider::class,
        AIArmada\Addressing\Geography\UnitedKingdom\UnitedKingdomGeographyProvider::class,
        AIArmada\Addressing\Geography\China\ChinaGeographyProvider::class,
        AIArmada\Addressing\Geography\Russia\RussiaGeographyProvider::class,
        AIArmada\Addressing\Geography\Germany\GermanyGeographyProvider::class,
        AIArmada\Addressing\Geography\France\FranceGeographyProvider::class,
        AIArmada\Addressing\Geography\Italy\ItalyGeographyProvider::class,
        AIArmada\Addressing\Geography\Japan\JapanGeographyProvider::class,
        AIArmada\Addressing\Geography\UnitedStates\UnitedStatesGeographyProvider::class,
        AIArmada\Addressing\Geography\Spain\SpainGeographyProvider::class,
        AIArmada\Addressing\Geography\Poland\PolandGeographyProvider::class,
        AIArmada\Addressing\Geography\Netherlands\NetherlandsGeographyProvider::class,
        AIArmada\Addressing\Geography\Nigeria\NigeriaGeographyProvider::class,
        AIArmada\Addressing\Geography\Ethiopia\EthiopiaGeographyProvider::class,
        AIArmada\Addressing\Geography\DemocraticRepublicOfCongo\DemocraticRepublicOfCongoGeographyProvider::class,
        AIArmada\Addressing\Geography\Tanzania\TanzaniaGeographyProvider::class,
        AIArmada\Addressing\Geography\Kenya\KenyaGeographyProvider::class,
        AIArmada\Addressing\Geography\Sudan\SudanGeographyProvider::class,
        AIArmada\Addressing\Geography\Uganda\UgandaGeographyProvider::class,
        AIArmada\Addressing\Geography\Algeria\AlgeriaGeographyProvider::class,
    ],
],
```

Providers define country address levels such as state, district, municipality or locality. Resolve the profile by country with `CountryAddressProfileResolver`; do not assume that a provider level has the same meaning in every country.

A `CountryGeographyProvider` also has a stable `providerKey()`. Keep that key unchanged when its imported `AddressAreaSource` key changes: it owns provider-seeded areas, aliases, roles, relationships, and State links across reseeds. The source key identifies a particular feed, while the provider key identifies its long-lived owner.

When a provider uses different first-level area roots for separate hierarchies, its State mappings may declare `hierarchy_types`. The same canonical `State` can then resolve the correct root for postal and administrative selectors independently.

Country-specific formatters are configured separately from geography providers:

```php
'formatters' => [
    AIArmada\Addressing\Geography\Malaysia\MalaysiaAddressFormatter::class,
    AIArmada\Addressing\Geography\Singapore\SingaporeAddressFormatter::class,
    AIArmada\Addressing\Geography\Indonesia\IndonesiaAddressFormatter::class,
    AIArmada\Addressing\Geography\Brunei\BruneiAddressFormatter::class,
    AIArmada\Addressing\Geography\Bahrain\BahrainAddressFormatter::class,
    AIArmada\Addressing\Geography\Bangladesh\BangladeshAddressFormatter::class,
    AIArmada\Addressing\Geography\Egypt\EgyptAddressFormatter::class,
    AIArmada\Addressing\Geography\India\IndiaAddressFormatter::class,
    AIArmada\Addressing\Geography\Jordan\JordanAddressFormatter::class,
    AIArmada\Addressing\Geography\Kuwait\KuwaitAddressFormatter::class,
    AIArmada\Addressing\Geography\Morocco\MoroccoAddressFormatter::class,
    AIArmada\Addressing\Geography\Oman\OmanAddressFormatter::class,
    AIArmada\Addressing\Geography\Pakistan\PakistanAddressFormatter::class,
    AIArmada\Addressing\Geography\Qatar\QatarAddressFormatter::class,
    AIArmada\Addressing\Geography\SaudiArabia\SaudiArabiaAddressFormatter::class,
    AIArmada\Addressing\Geography\SouthAfrica\SouthAfricaAddressFormatter::class,
    AIArmada\Addressing\Geography\Turkiye\TurkiyeAddressFormatter::class,
    AIArmada\Addressing\Geography\UnitedArabEmirates\UnitedArabEmiratesAddressFormatter::class,
    AIArmada\Addressing\Geography\UnitedKingdom\UnitedKingdomAddressFormatter::class,
    AIArmada\Addressing\Geography\China\ChinaAddressFormatter::class,
    AIArmada\Addressing\Geography\Russia\RussiaAddressFormatter::class,
    AIArmada\Addressing\Geography\Germany\GermanyAddressFormatter::class,
    AIArmada\Addressing\Geography\France\FranceAddressFormatter::class,
    AIArmada\Addressing\Geography\Italy\ItalyAddressFormatter::class,
    AIArmada\Addressing\Geography\Japan\JapanAddressFormatter::class,
    AIArmada\Addressing\Geography\UnitedStates\UnitedStatesAddressFormatter::class,
    AIArmada\Addressing\Geography\Spain\SpainAddressFormatter::class,
    AIArmada\Addressing\Geography\Poland\PolandAddressFormatter::class,
    AIArmada\Addressing\Geography\Netherlands\NetherlandsAddressFormatter::class,
    AIArmada\Addressing\Geography\Nigeria\NigeriaAddressFormatter::class,
    AIArmada\Addressing\Geography\Ethiopia\EthiopiaAddressFormatter::class,
    AIArmada\Addressing\Geography\DemocraticRepublicOfCongo\DemocraticRepublicOfCongoAddressFormatter::class,
    AIArmada\Addressing\Geography\Tanzania\TanzaniaAddressFormatter::class,
    AIArmada\Addressing\Geography\Kenya\KenyaAddressFormatter::class,
    AIArmada\Addressing\Geography\Sudan\SudanAddressFormatter::class,
    AIArmada\Addressing\Geography\Uganda\UgandaAddressFormatter::class,
    AIArmada\Addressing\Geography\Algeria\AlgeriaAddressFormatter::class,
],
```

`FormatAddressAction` resolves a formatter by `AddressData::countryCode` and falls back to the generic formatter when no country formatter is registered. This keeps formatting independent from geography seeding.

## Navigation Links

Navigation link columns (`google_maps_url`, `waze_url`, `navigation_links`) are part of the `addresses` and `address_snapshots` table schemas. They use the configured JSON column type for `navigation_links`.

Manual URLs always win over generated URLs. See `12-navigation-links.md` for full priority rules.

## Place metadata

Address areas store the place itself. Alternate names, address roles, typed
relationships and postcode coverage are stored separately. Alternate names
have a type such as `common` or `abbreviation`; there is no unused language
column because locale-aware name selection is not implemented.

Typed relationships have their own `source`. A manual relationship and a provider relationship with the same parent, child, type, and hierarchy remain separate records, so reseeding a provider cannot remove a manual edge.

## Defaults

```php
'defaults' => [
    'country_code' => env('ADDRESS_DEFAULT_COUNTRY_CODE'),
    'locale' => env('ADDRESS_DEFAULT_LOCALE'),
],
```

When configured, `ADDRESS_DEFAULT_COUNTRY_CODE` is trimmed and normalized to
uppercase during package boot. Invalid or blank values are treated as unset.

The configured model classes must extend the corresponding core model. Core
relations and normalization use `ModelResolver`, so host subclasses are
applied consistently.

## Area Sources

```php
'area_sources' => [
    // App\Addressing\MalaysiaAddressAreaSource::class,
],
```

Register your `AddressAreaSource` implementations here. They become available to the `address:import-areas` command.

## OneMap Postcodes

```php
'onemap' => [
    'base_url' => env('ONEMAP_BASE_URL', 'https://www.onemap.gov.sg/api'),
    'email' => env('ONEMAP_EMAIL'),
    'password' => env('ONEMAP_PASSWORD'),
    'timeout' => 10,
    'retries' => 2,
],
```

Singapore postcodes are resolved on demand through SLA's OneMap API instead
of being bundled. Register for a OneMap account, then set `ONEMAP_EMAIL` and
`ONEMAP_PASSWORD`. The client caches the access token until shortly before
its reported expiry, refreshes it once on a 401, and retries 429/5xx
responses with backoff. OneMap usage requires attribution; see
`05-country-data.md`.
