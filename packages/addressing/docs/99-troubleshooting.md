---
title: Troubleshooting
---

# Troubleshooting

## Slow name lookups on large geography tables

Name matching uses case-insensitive `LOWER(name)` lookups, which cannot use the plain `name` index. The migrations ship `LOWER(name)` functional indexes on countries, states, cities, areas, and area names (with a normalized-column fallback on drivers without functional-index support). If lookups still scan, verify the indexes exist under your configured table prefix.

## Missing Countries After Migration

Run the seed command:

```bash
php artisan address:seed-countries
```

## Missing Malaysia States or Federal Territories

`address:seed-countries` only seeds ISO countries. For structured Malaysia geography, run `app(SeedCountryGeographiesAction::class)->execute('MY')` after countries exist. This also imports the hierarchy and creates explicit State↔AddressArea links. Postal localities and administrative districts are separate branches; neither requires a postal-town record.

## Missing Singapore Districts or Planning Areas

Same cause as above with `execute('SG')`. The five states are CDC districts, not URA regions: they link to matching district areas but are never parents of planning areas or postal sectors, so do not expect a state root above those trees.

## OneMap Resolution Failures

`ResolveSingaporePostalCodesAction` throws `OneMapException` when `ONEMAP_EMAIL` or `ONEMAP_PASSWORD` is missing, when authentication fails, or when search requests keep failing after retries. An `Area not found` error instead means the sector tree is missing — seed SG geographies first. Postcodes OneMap does not know are reported in `invalid`, not thrown.

## Missing Indonesia Provinces or Regencies

Same cause as above with `execute('ID')`. Indonesia has 38 provinces; the
seven ISO geographical units (island groups) were removed from the bundled
state data, and seeding deletes any stragglers, so a plain `Papua` always
resolves to the province. Assigning a district requires its regency (or the
province state) to be selected first, because districts validate through the
regency level.

## Missing Brunei Districts or Mukims

Same cause as above with `execute('BN')`. Brunei-Muara has 18 mukims, not
17 — older references predate the current 39-mukim split. Mukims carry no
official numeric codes; the bundled areas link to districts by parent only.

## Missing States for the Newer Providers

Same cause with the matching `execute()` code (`BH`, `QA`, `KW`, `OM`,
`AE`, `JO`, `SA`, `EG`, `MA`, `PK`, `BD`, `IN`, `TR`, `GB`, `ZA`).
Single-level providers expose no assignable area roles — the state *is*
the area — so an empty assignment list there is expected, not a seeding
failure. Bangladesh and Morocco are the only two with a second level
(`district` and `province` roles respectively). A `firstOrFail` on the
state link for Bahrain, Saudi Arabia, Türkiye, or Morocco
means the numeric ISO code lookup missed; those codes are correct as
shipped (BH has no `16`, SA has no `13`).

## Missing States for the Second Batch

Same cause with the matching `execute()` code (`CN`, `RU`, `DE`,
`FR`, `IT`, `JP`, `US`, `ES`, `PL`, `NL`, `NG`, `ET`, `CD`, `TZ`,
`KE`, `SD`, `UG`, `DZ`). All of these are single-level except Spain,
whose provinces use the `province` role. Ethiopia deletes dissolved
`SN` rows on seed — a missing SNNPR is correct, not data loss. A
missing Taiwan under China is also correct: it carries its own `TW`
country code. The US military codes (`AA`/`AE`/`AP`) and `UM` are
intentionally not areas even though they exist as global states.

## Area Assignment Role Rejected

`SyncAddressAreaAssignmentsAction` throws `The selected address area role is not defined by the country address profile.` for unknown roles — check the role against `CountryAddressProfileResolver::definitionForRole()` for that country. It throws `The selected role is not an assignable area role.` for `state_id` and other non-area roles: pass state through the action's `stateId` parameter instead of the assignments map.

## Address Has Text State/City but No Relations

Free-text `state` / `city` columns do not automatically populate `state_id` / `city_id`. Set the foreign keys when you want `Address::state()` / `Address::city()` relations.

## Duplicate Source IDs During Area Import

The `address_areas` table has a unique constraint on `(source, source_id)`. If you encounter duplicate key errors, check that your `AddressAreaSource` does not yield duplicate `sourceId` values for the same `key()`.

## Missing Parent During Area Import

When importing areas with a parent hierarchy, ensure parents are imported before their children, or use a `parent_source_id` that already exists in the database. The import action resolves parents by `source + parent_source_id` lookup.

## JSON Column Type Issues

If you get JSON encoding errors, ensure your database supports the configured column type. For PostgreSQL:

```env
ADDRESS_JSON_COLUMN_TYPE=jsonb
```

For SQLite or MySQL:

```env
ADDRESS_JSON_COLUMN_TYPE=json
```

## Navigation URL Not Showing

Make sure the addressing migrations have run. Check the `google_maps_url` and `waze_url` columns exist on your `addresses` table.

If a manual URL is set but not appearing in the output, verify it passes `NormalizeNavigationUrl` validation — it must have an `http://` or `https://` scheme.

## Command Not Found

If `address:seed-countries` is not available, publish the vendor assets:

```bash
php artisan vendor:publish --provider="AIArmada\Addressing\AddressingServiceProvider"
```
