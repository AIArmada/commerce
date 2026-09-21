# Addressing Seeding

Applies to the seed actions, commands, seeders, and readers that populate
addressing reference data.

- `SeedAddressingAction` is the composition root: countries, references,
  states, cities, then every configured geography. Apps call the bundled
  `AIArmada\Addressing\Database\Seeders\AddressingSeeder` or run
  `address:seed`; they never hand-roll the orchestration.
- The granular `address:seed-*` commands exist for partial work only.
- City volume is config, not app code:
  `addressing.seed.full_city_countries` filters cities outside production.
  Production always seeds the full city dataset.
- The geography seed rebuilds provider-sourced roles, names, and
  relationships from scratch while area rows upsert by `source_id` with
  stable IDs. Reseeding is safe and is the way dataset changes reach a
  database.
- Reseed safety limits: renamed identity keys (`source_id`, state code,
  city name, `iso2`) orphan existing links onto the old row — renames
  need a remap migration, not just a reseed. External tables must link
  to area/state/city/country IDs only, never to rebuilt link-table IDs.
  Records on deactivated areas need a show-with-warning or remap path.
- Streaming seed readers index raw bytes: use
  `mb_strlen($chunk, '8bit')`, never bare `mb_strlen` — Pint's
  `mb_str_functions` will otherwise corrupt multibyte parsing.
- `SeedCountryGeographiesAction` is final: test orchestration with a tiny
  real provider (e.g. Saint-Barthélemy) plus config, not subclass fakes.
