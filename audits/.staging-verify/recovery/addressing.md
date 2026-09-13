End-to-end review of packages/addressing (domain package; no routes/jobs/widgets — Filament UI is in filament-addressing, out of scope).

## FINDINGS

### F1 — medium / bug — src/Data/AddressData.php:145-152 — Non-numeric lat/lng silently coerced to 0.0
`floatOrNull()` does `return (float) $value;` with no `is_numeric` check, so `latitude: "abc"` becomes `0.0` — a *valid* coordinate (Gulf of Guinea), not null. The seed actions in this same package already guard with `is_numeric` (e.g. SeedAddressStatesAction.php:58-59), so this is inconsistent.
Evidence: `if ($value === null || $value === '') return null; return (float) $value;`
Recommendation: return null for non-numeric input (mirror the seed actions), or throw InvalidArgumentException. Confidence: high.

### F2 — medium / bug — src/Actions/ImportAddressAreasAction.php:176-192 — Re-import never quiesces; every row always "updated"
`$data` includes `'synced_at' => CarbonImmutable::now()`, so `$existing->fill($data)` is always dirty and every re-import reports all rows as updated (plus `updated_at` churn), never skipped.
Evidence: `'synced_at' => CarbonImmutable::now(),` … `if ($existing->isDirty()) { $existing->save(); $updated++; }`
Recommendation: exclude `synced_at` from the dirty check (compare before stamping, or only stamp when otherwise dirty). Confidence: high.

### F3 — medium / bug — src/Actions/ImportAddressAreasAction.php:112-116,174 — Dry-run skips validation; import force-reactivates areas
(a) Dry-run `continue`s before the parent/hierarchy resolution, so missing parents and hierarchy violations are never reported — dry-run over-reports success. (b) `'is_active' => true` is unconditional, so re-import silently reactivates areas an operator deliberately deactivated (outside the SeedCountryGeographiesAction deactivate-then-sync flow, where it is intentional).
Recommendation: run parent/hierarchy validation before the dry-run branch; only set `is_active=true` on create (or add an explicit `--reactivate` flag). Confidence: high.

### F4 — medium / security — src/Support/NormalizeNavigationUrl.php:1-32 (dead code) + src/Data/AddressData.php:55-57 — Stored navigation/map URLs never validated
`NormalizeNavigationUrl` (FILTER_VALIDATE_URL + http/https scheme check) has zero call sites — verified by grep. `AddressData`/`Address` persist `google_maps_url`, `waze_url`, `navigation_links` raw, and `BuildAddressNavigationLinksAction.php:40-48` returns caller-stored values verbatim. A `javascript:` URL persists; exploitability as stored XSS depends on filament-addressing rendering links as clickable hrefs (not verified here).
Recommendation: apply `NormalizeNavigationUrl` in `AddressData::from()` (or `Address::saving`), and treat `navigation_links.*.url` the same. Confidence: med (write-side gap confirmed; render-side impact unverified).

### F5 — medium / performance — src/Models/Address.php:67-73 + src/Actions/NormalizeAddressDataAction.php:190-196 — Every Address save pays up to 4 Schema::hasTable + 3 LOWER(name) lookups
The `saving` hook unconditionally runs the full normalizer — including `Schema::hasTable` (information_schema round-trips, uncached) for country/state/city plus the resolver's — and case-insensitive name scans, even when only non-address attributes (e.g. `validation_status`, `metadata`) change.
Recommendation: skip normalization when no address inputs are dirty (`isDirty([...])`), and cache table-existence (config/request cache). Confidence: high.

### F6 — medium / performance — database/migrations/2001_01_01_000002,000003 — Missing indexes on states(name), cities(name)
`NormalizeAddressDataAction` filters `LOWER(name)` on both tables and `SeedAddressCitiesAction` filters `(country_id, name)`; cities can exceed 150k rows yet only `country_id`/`state_id` are indexed. Countries table also lacks a `name` index (small table — negligible, but same query pattern at NormalizeAddressDataAction.php:82-84).
Recommendation: add `index('name')` (or composite `(country_id, name)`) to cities/states; consider `pg_trgm`/functional index if LOWER() scans persist. Confidence: high.

### F7 — medium / performance+bug — src/Actions/ImportAddressAreasAction.php:36-214 — Per-row N+1 and non-atomic import
Each row issues country + existing + parent SELECTs plus a relationship DELETE and an updateOrCreate, with no transaction around the loop — the CSV-command path can leave a partial import on crash (only `SeedCountryGeographiesAction` wraps it in a transaction).
Recommendation: cache countries by iso2, preload existing/parent maps (or chunk + upsert), and wrap `execute()` in a DB transaction. Confidence: high.

### F8 — medium / bug — src/Actions/SaveAddressAreaAction.php:83-105 — Record save + relationship rewrite not atomic
`$record->save()` then relationship delete + conditional create run outside any transaction — a crash between leaves hierarchy links deleted.
Recommendation: wrap save + relationship sync in `DB::transaction()`. Confidence: high.

### F9 — low / bug — src/Support/CsvAddressAreaSource.php:142 + src/Commands/ImportAddressAreasCsvCommand.php:30-36 — Malformed CSV JSON crashes instead of failing gracefully
`jsonArray()` uses `JSON_THROW_ON_ERROR`, but `JsonException` is not an `InvalidArgumentException`, so the command's catch misses it and prints a stack trace. Also the strict column-count check rejects rows with trailing commas.
Recommendation: catch `JsonException` (or wrap into InvalidArgumentException) in `jsonArray()`; optionally tolerate trailing empty columns. Confidence: high.

### F10 — low / bug — src/Traits/HasAddresses.php:106-115 — Re-attach ignores $label
The existing-pivot path updates only `is_primary`; a changed `$label` is silently dropped.
Recommendation: update `label` when provided and different. Confidence: high.

### F11 — low / bug — src/Casts/AddressDataCast.php:44-46 — Array set-path skips normalization
`AddressData` values go through `toArray()` (normalized shape) but raw arrays are `json_encode`d as-is — no alias mapping, trim, or country-code upper-casing — so the two paths persist inconsistent shapes.
Recommendation: route arrays through `AddressData::from($value)->toArray()`. Confidence: high.

### F12 — low / bug — src/Models/Address.php:67-73 — formatted_address never recomputed; derived fields mass-assignable
The `saving` hook normalizes IDs but preserves caller-supplied `formatted_address`/`formatted_lines`/`components` verbatim, so stored formatted output can drift from the lines. All three plus `raw_address` are `$fillable`.
Recommendation: regenerate (or clear) formatted fields when address inputs are dirty; consider un-filling derived fields. Confidence: med.

### F13 — low / security — src/Models/Address.php:110-115 — Trust-sensitive fields mass-assignable
`validation_status`, `validated_at`, `provider`, `provider_place_id`, `provider_payload` are `$fillable`, so any consumer passing user input to `Address::create()` lets callers forge verification state. No in-package controller does this (mitigating), but the model is the last line of defense.
Recommendation: `$guarded` these fields or document trusted-write-only and enforce at the Filament layer. Confidence: med.

### F14 — low / security — src/Support/AddressOwnerGuard.php:79-134 — Morph-type oracle via distinct error messages
Unresolvable vs missing vs inaccessible `addressable_type` values produce distinguishable `AuthorizationException` messages, letting a caller probe which model classes exist / which IDs are present.
Recommendation: use one generic message for all three branches. Confidence: med.

### F15 — low / performance — src/Actions/SeedAddressCitiesAction.php:57-89, src/Actions/SeedAddressStatesAction.php:53-60, src/Support/AddressAreaHierarchy.php:15-48 — Unbounded seed N+1 and full-table parent options
City/state seeds do one SELECT (+ maybe INSERT/UPDATE) per row for up to ~150k rows with no chunk/upsert (seed-time only, mitigated by country/state pre-caching). Separately, `parentOptions()` loads *all* of a country's areas into memory with O(n·depth) cycle checks — fine for Malaysia (~1.8k rows) but an OOM risk for large geographies behind an admin select.
Recommendation: bulk upsert/chunk for seeds; paginate or search-driven parent picker instead of full preload. Confidence: high (seeds) / med (parentOptions at scale).

Minor notes (no severity): `Str::slug()` yields `''` for non-Latin names (ImportAddressAreasAction.php:110, SaveAddressAreaAction.php:64); `Address::deleting` uses query-builder deletes and is skipped entirely by `Address::where()->delete()` leaving orphaned pivots/assignments (Address.php:74-78); `AddressAreaAssignment` intentionally has no owner columns (migration 000017; owner cutover covers only addresses/addressables/snapshots) so it relies fully on parent-Address scoping — entry points guard this (`SyncAddressAreaAssignmentsAction.php:37`), and pivot owner columns are written but never read-filtered (`AddressOwnerGuard::applyToRelation` scopes only the address table — acceptable since pivots are constrained to one owner-verified attachable); `attachAddress`/`setPrimaryAddress` on an unsaved model throw a low-level QueryException instead of a guard error.

## POSITIVES (brief)
- Owner scoping is thorough: `HasOwner`+`HasOwnerScopeConfig` on Address/Addressable/Snapshot, write guards on both pivot and snapshot `saving`, event-owner fallback path, `lockForUpdate` + demote-primary inside transactions, owner cutover migration rejects legacy ownerless rows.
- Injection-safe: all `whereRaw`/`orderByRaw` use bindings; search LIKE input is `addcslashes`-escaped with `ESCAPE '\'`; search limit clamped to 1–100; no `eval`/`unserialize`/HTTP calls (no SSRF/deserialization surface); console-only file paths.
- Repo rules honored: uuid PKs, `foreignUuid` without constraints (no FKs), no SoftDeletes, table/model resolvers config-driven and validated, request-attribute caches (Octane-safe), singleton actions stateless, no mutable static state.

## COVERAGE
Read: CONTEXT.md, config, all 15 models, 14 actions, 7 commands (2 import + 5 seed), all Support/Data/Casts/Traits/Geography files, key migrations (addresses, addressables, snapshots, areas, assignments, relationships, countries, states, cities, postal codes, owner cutover). No tests dir exists in-package. Checklist items with no issue found: unscoped counts/DB::table on owner tables (only global link tables + migration guard), route bindings/jobs/widgets (none in package), auth gaps (no HTTP surface), XSS output (no HTML built in-package), path traversal (fixed `__DIR__` paths; CSV path is console-operator input), cache stampedes (no cache use), Octane static state (none).