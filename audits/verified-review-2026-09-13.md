---
title: Verified End-to-End Review — packages/* (bugs, security, performance)
date: 2026-09-13
scope: packages/* (67 packages)
method: e2e reviews + prior verified audit, per-finding verification pass
---

# Verified End-to-End Review — packages/* (bugs, security, performance)

Date: 2026-09-13. Scope: all 67 packages under `packages/*`.
Sources merged per package: (1) e2e review findings (round 1 recovered verbatim
from the interrupted 2026-09-12 session + round 2 fresh reviews 2026-09-13),
(2) prior verified audit `all-packages-audit-2026-09-12.md` chunks,
(3) per-finding verification verdicts checked against CURRENT source
(`CONFIRMED` / `DOWNGRADED` / `FALSE` / `FIXED` / `UNVERIFIED` / `ADOPTED`,
with `DUP` where both sources reported the same issue — counted once).

## Verdict counts

| Verdict | critical | high | medium | low | n/a |
|---------|----------|------|--------|-----|-----|

| CONFIRMED | 7 | 115 | 338 | 284 | 0 |
| ADOPTED | 6 | 81 | 236 | 97 | 55 |
| DOWNGRADED | 0 | 1 | 15 | 19 | 1 |
| FALSE | 2 | 5 | 10 | 7 | 2 |
| FIXED | 0 | 14 | 8 | 2 | 1 |
| UNVERIFIED | 0 | 1 | 2 | 1 | 0 |

Actionable (CONFIRMED + ADOPTED + DOWNGRADED): **1255**.
Rejected as false: **26**. Already fixed in current source: **25**.
Unverified: **4**. Duplicate e2e/audit pairs merged: **295**.

## Limitations

- Verdicts reflect CURRENT source at verification time; code fixed after that
  (e.g. the 2026-09-13 migration batch) is marked FIXED where observed.
- DUP pairs were judged by description + file:line overlap; near-duplicates with
  different scopes were kept separate.
- UNVERIFIED items need runtime/prod-data confirmation (reason stated per item).

# Part 1 — Round-1 packages (29)


---

## addressing

### E2E findings (verbatim)

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

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### addressing
Bugs:
- `Models/Address.php:74-78` MEDIUM — `deleting` bulk deletes pivots (skips events) + nulls snapshots, no txn.
- `ImportAddressAreasAction.php:36-214` MEDIUM (was HIGH) — 3–6 queries/row, no txn/chunk; offline import perf + partial on abort.
- `ImportPostalCodesAction.php:25-132` MEDIUM (was HIGH) — per-row `DB::transaction` exists (`:63`), but still per-row queries, no chunk.
Security: clean — `AddressOwnerGuard` + `Addressable:saving` + morph checks; search bound params + clamped limit; seed `DB::table` global-only.
Performance: DONE (2026-09-13, §8 item 9) — `LOWER(name)` full scans MEDIUM — `NormalizeAddressDataAction:83,108,137`, `SearchAddressAreas:84-96`, `HierarchyResolver:40,69`; add functional/lower index; cache `Schema::hasTable` (`Normalize:191-196` hits info-schema per save). Fixed: `LOWER(name)` functional indexes folded into the geography creates. `Addressable` owner + `(type/id)` + `is_primary` indexes present.

### Migration-batch rows (§8, code may already be fixed)
| 9 | addressing `LOWER(name)` indexes (§1) | Folded into the geography creates (normalized-column fallback) | `packages/addressing/docs/99-troubleshooting.md` |

### Verification verdicts

- [R1:#1] CONFIRMED medium bug | src/Data/AddressData.php:151 | "Non-numeric lat/lng" still (float) w/o is_numeric; "abc"→0.0 valid coord, unlike seed actions
- [R1:#2] CONFIRMED medium bug | src/Actions/ImportAddressAreasAction.php:176 | "Re-import never quiesces" synced_at in $data ⇒ fill always dirty; every re-import all-updated
- [R1:#3] CONFIRMED medium bug | src/Actions/ImportAddressAreasAction.php:112 | "Dry-run skips validation" dry-run returns before parent/hierarchy checks; is_active=true unconditional
- [R1:#4] CONFIRMED medium sec | src/Support/NormalizeNavigationUrl.php:9 | "Stored navigation URLs" zero call sites; raw persist + verbatim return; render-side XSS unverified
- [R1:#5] CONFIRMED medium perf | src/Models/Address.php:69 | "Every Address save" saving hook always normalizes; uncached Schema::hasTable + LOWER scans
- [R1:#6] FIXED medium perf | database/migrations/2001_01_01_000002:25 | "Missing indexes states" name indexes now in creates (states/cities/countries _lower_index)
- [R1:#7] CONFIRMED medium perf+bug | src/Actions/ImportAddressAreasAction.php:36 | "Per-row N+1" per-row country+existing+parent SELECTs, delete+updateOrCreate, no txn
- [R1:#8] CONFIRMED medium bug | src/Actions/SaveAddressAreaAction.php:83 | "Record save relationship" save() then relationship delete+create outside any txn
- [R1:#9] CONFIRMED low bug | src/Support/CsvAddressAreaSource.php:142 | "Malformed CSV JSON" JSON_THROW_ON_ERROR escapes InvalidArgumentException catch; strict col count
- [R1:#10] CONFIRMED low bug | src/Traits/HasAddresses.php:106 | "Re-attach ignores label" existing-pivot path updates is_primary only; changed $label dropped
- [R1:#11] CONFIRMED low bug | src/Casts/AddressDataCast.php:44 | "Array set-path skips" arrays json_encoded raw; no alias/trim/uppercase normalization
- [R1:#12] CONFIRMED low bug | src/Models/Address.php:69 | "Formatted never recomputed" saving hook preserves formatted_* verbatim; derived fields fillable
- [R1:#13] CONFIRMED low sec | src/Models/Address.php:110 | "Trust-sensitive fields fillable" validation_*/provider_* fillable; forgeable via naive create()
- [R1:#14] CONFIRMED low sec | src/Support/AddressOwnerGuard.php:79 | "Morph-type oracle via" distinct unresolvable/missing/inaccessible messages; class/ID probing
- [R1:#15] CONFIRMED low perf | src/Actions/SeedAddressCitiesAction.php:57 | "Unbounded seed N+1" per-row SELECT+write to ~150k; parentOptions full-table get
- [AUD:B1] ADOPTED medium bug | src/Models/Address.php:74 | deleting bulk-deletes pivots (skips events) + nulls snapshots, no txn; confirmed
- [AUD:B2] ADOPTED medium perf+bug | src/Actions/ImportAddressAreasAction.php:36 | DUP R1:#7 counted once; 3-6 queries/row, no txn/chunk
- [AUD:B3] ADOPTED medium perf | src/Actions/ImportPostalCodesAction.php:25 | Per-row txn exists (:63) but per-row queries + coverage rewrite, no chunk
- [AUD:B4] ADOPTED medium perf | database/migrations/2001_01_01_000003:26 | DUP R1:#6 counted once; FIXED 2026-09-13 indexes folded into creates
- [AUD:Q#9] ADOPTED medium perf | database/migrations/2001_01_01_000003:26 | DUP AUD:B4 counted once; §8 row 9 LOWER(name) indexes done

---

## affiliate-network

### E2E findings (verbatim)

End-to-end review: packages/affiliate-network. 16 findings (2 high, 8 medium, 6 low) + positives.

HIGH
1. [high/bug] src/Models/AffiliateSite.php:104-109 — Site delete orphans grandchildren. `$site->offers()->delete()` is a mass delete, which never fires `AffiliateOffer::deleting` (AffiliateOffer.php:197-201), so the deleted site's offers' creatives, applications, and links are left orphaned. Fix: iterate (`chunkById` + `$offer->delete()`) or explicitly delete children first.
2. [high/bug] src/Console/Commands/ArchiveExpiredOffersCommand.php:30-33 — Archive command SQL-errors when owner scoping is on. `OwnerBatchRunner(AffiliateOffer::class)` discovers owners via `DB::table(offers)->select(owner_type, owner_id)` (commerce-support OwnerBatchRunner.php:87-96; OwnerTupleColumns falls back to owner_type/owner_id since AffiliateOffer has no ownerScopeConfig), but the offers table has no owner columns (scoped via site). Console run with `affiliate-network.owner.enabled=true` and no ambient owner crashes. Fix: run per-site owners (batch over AffiliateSite) or add explicit-global handling.

MEDIUM
3. [medium/bug+security] src/Actions/UpdateOffer.php:15-28 — Unguarded mass update. Raw `$data` is passed to `update()` with fillable including `site_id, category_id, rate_source, source_checksum, external_program_id, subject_*`; unlike CreateOffer there is no OwnerWriteGuard re-validation, so an offer can be moved across sites/owners and the `synced` rate lock flipped. The Filament form exposes `site_id` as an editable select, so this is reachable from Edit. Fix: allowlist updatable fields; re-guard site/category changes like CreateOffer.
4. [medium/bug] database/migrations/2000_01_01_000003…: `status` default `'pending'` is not a member of `OfferStatus` (draft/published/archived). Any row taking the DB default (raw insert, future path bypassing CreateOffer) throws on enum cast at read. Fix: default `'draft'` + corrective migration.
5. [medium/bug] src/Listeners/RecordNetworkConversionForOrder.php:27-77,101-119 — Conversion recording is neither idempotent nor request-safe. A redelivered `CommissionAttributionRequired` double-increments conversions/revenue (no check of `metadata.network_attribution` before writing), and attribution is read via `request()->cookie()`, which is empty if the event is ever handled off-request (queue), silently dropping attribution. Fix: early-return if order metadata already has network_attribution; attach attribution to the event at dispatch.
6. [medium/bug] src/Actions/ApplyToOffer.php:46-96 — Re-apply race + wrong cooldown base. Check-then-`create()` against unique(offer_id, affiliate_id) lets concurrent double-applies throw an unhandled QueryException (500). Cooldown is measured from `updated_at` rather than `rejected_at`, so any touch resets it. Fix: catch unique violation and return existing; use `rejected_at ?? updated_at`.
7. [medium/bug/integrity] src/Services/OfferLinkService.php:36-56 — `createLink` enforces nothing: no published/active/approval checks and no `target_url` validation; arbitrary target URLs persist as stored open redirects (blast radius limited by signed redirect URLs). The Filament marketplace gates on approval, but the domain service is unguarded. Fix: require active offer + approval (or explicit reason), validate http(s) target_url.
8. [medium/performance] src/Services/OfferImportService.php:47-55,73-93 + LocalProgramReader.php:35-47 — Sync is N+1 with no transaction and fragile error handling: per-subject SELECT + write (up to 500/program), `syncAll` iterates an unbounded program list (unbounded `pluck`), and only `OfferNotFoundException` is caught per program, so one slug-collision QueryException aborts the entire remaining sync. Fix: per-subject try/catch with `failed` counting, upsert/chunk, cap programs.
9. [medium/performance+bug] src/Console/Commands/ArchiveExpiredOffersCommand.php:25-51 — Unbounded `->get()` + per-row `update()` (memory/N+1); sets `status=Archived` but never `archived_at`; `--older-than` unvalidated (negative/zero archives almost everything). Fix: `chunkById`, set `archived_at`, clamp option to >= 0.
10. [medium/performance] migrations — Missing indexes for hot paths: `offers.ends_at` (archive scan), `offers(site_id, external_program_id, subject_key)` (per-subject sync lookup; only a single-col index on external_program_id exists), `offers(status, visibility)` (public marketplace lookups).

LOW
11. [low/security] migration 000002 — `offer_categories.slug` is globally unique while the model is owner-scoped; one tenant's slug blocks all others. Fix: unique per owner tuple.
12. [low/security] src/Services/Catalog/RemoteCatalogClient.php:34,110-121 — Token fail-open: `decrypt()` failure returns null and the request still goes out anonymously; `programId` is interpolated raw into the path (CLI `--program` or remote-supplied). Fix: throw on undecryptable token; `rawurlencode($programId)`.
13. [low/security] TrackNetworkLinkCookie.php + Listener :54-69 — Attribution window trusts client `clicked_at`; tamper-resistance depends entirely on EncryptCookies being active in the configurable middleware group, and there is no server-side click record. Fix: signed cookie value or persist click server-side.
14. [low/bug] ApplyToOffer.php:68, ApproveApplication.php:27, UpdateOffer.php:23, OfferManagementService.php:157,177 — `fresh()` can return null (concurrent delete) into non-nullable typed event constructors/returns → TypeError. Fix: null-coalesce to the in-memory model.
15. [low/bug] src/Actions/CreateOffer.php:52-58 — No domain validation: missing `name` → undefined-array-key error in `Str::slug($data['name'])`; rate/URL/status values unvalidated below Filament. Fix: validate required name/slug, rate ranges, URL formats.
16. [low/performance] OfferManagementService.php:203-214 — `getApprovedOffers` is unbounded (pluck + whereIn + get); cookie middleware runs a 3-relation `resolveLink` on every web request when checkout is enabled. Fix: paginate/cap; skip middleware work when no `anl` param present (already mostly true, but eager loads run per hit).

Positives (verified): SSRF posture is good — PublicHttpUrlGuard + PinnedHttpClient + bounded 1 MB bodies + connect/total timeouts in RemoteCatalogClient and SiteContentFetcher; redirect route is signed + throttled with an http/https scheme allowlist; verification compares use hash_equals; link codes are crypto-random (`random_bytes`); money is int minor units throughout (`rate_fixed_minor`, `revenue`, orders `grand_total` unsignedBigInteger); uuid PKs, no FK constraints/cascades and no SoftDeletes per repo rules; owner scoping delegates to commerce-support with documented explicit-global windows (verified BelongsTo constraints make the write-guard check target the correct related row); Octane-safe static flag reset in `finally`; custom link params merged so they cannot override `anl`; syncAll isolates per-program failures; slugs unique per site; no raw SQL/deserialization/file-ops in the package; central Pest coverage exists (tests/src/AffiliateNetwork: 5 action, 5 service, 8 model, 3 feature suites).

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### affiliate-network
Bugs:
- `ApplyToOffer:45-96` MEDIUM — check-then-create, no txn/lock/23000 rescue; race → 500 via `unique(offer_id,affiliate_id)`.
- `OfferLinkService:36-56` MEDIUM — arbitrary `target_url` stored; redirect only checks scheme → open redirect.
- Clicks/conversions gameable MEDIUM — raw `increment()`; no bot/dedup/idempotency.
- Counters/sync internals fillable MEDIUM (`OfferLink clicks/conversions/revenue`; `Offer source_checksum/last_synced_at`).
Security: `resolveLink:115-128` acceptable by design — explicit `withOwner(null)`, 64-bit `random_bytes` code, `signed` + `throttle:60,1`. `SiteContentFetcher:27-44` GOOD — `PublicHttpUrlGuard` + pinned client + timeouts + 1MB cap; strategies use `hash_equals`.
Performance: import bounded loop LOW — `OfferImportService:44-58` `array_slice(500)` loop, no chunk/cursor for large syncs.

### Verification verdicts

- [R1:#1] CONFIRMED high bug | src/Models/AffiliateSite.php:107 | "Site delete orphans" offers()->delete() skips Offer::deleting; creatives/apps/links orphaned
- [R1:#2] CONFIRMED high bug | src/Console/Commands/ArchiveExpiredOffersCommand.php:30 | "Archive command SQL-errors" OwnerBatchRunner selects owner cols offers table lacks; no ownerScopeConfig
- [R1:#3] CONFIRMED medium bug+sec | src/Actions/UpdateOffer.php:21 | "Unguarded mass update" raw $data incl site/category/rate_source; site moves only guarded when owner on
- [R1:#4] CONFIRMED medium bug | database/migrations/2000_01_01_000003:25 | "status default pending" 'pending' default not in OfferStatus; enum cast throws on read
- [R1:#5] CONFIRMED medium bug | src/Listeners/RecordNetworkConversionForOrder.php:27 | "Conversion recording neither" no idempotency check before write; request()->cookie() off-request empty
- [R1:#6] CONFIRMED medium bug | src/Actions/ApplyToOffer.php:46 | "Re-apply race cooldown" check-then-create no 23000 rescue; cooldown from updated_at; DUP AUD:B1 sev kept
- [R1:#7] CONFIRMED medium bug | src/Services/OfferLinkService.php:36 | "createLink enforces nothing" no active/approval/target_url checks; stored open redirect; DUP AUD:B2
- [R1:#8] CONFIRMED medium perf | src/Services/OfferImportService.php:47 | "Sync is N+1" per-subject SELECT+write; unbounded programIds; only OfferNotFoundException caught
- [R1:#9] CONFIRMED medium perf+bug | src/Console/Commands/ArchiveExpiredOffersCommand.php:25 | "Unbounded get per-row" ->get()+per-row update; archived_at never set; --older-than unvalidated
- [R1:#10] CONFIRMED medium perf | database/migrations/2000_01_01_000003:42 | "Missing indexes hot" no ends_at / (site,program,subject) / (status,visibility) indexes
- [R1:#11] CONFIRMED low sec | database/migrations/2000_01_01_000002:21 | "slug globally unique" global unique slug on owner-scoped model; one tenant blocks others
- [R1:#12] CONFIRMED low sec | src/Services/Catalog/RemoteCatalogClient.php:110 | "Token fail-open programId" decrypt fail → anonymous request; programId raw in path
- [R1:#13] CONFIRMED low sec | src/Listeners/RecordNetworkConversionForOrder.php:54 | "Attribution window trusts" client clicked_at; unsigned JSON cookie; no server click record
- [R1:#14] CONFIRMED low bug | src/Actions/UpdateOffer.php:23 | "fresh returns null" fresh() into non-nullable event ctors/returns at 5 sites; race TypeError
- [R1:#15] CONFIRMED low bug | src/Actions/CreateOffer.php:52 | "No domain validation" missing name → undefined key in Str::slug; rate/URL/status unvalidated
- [R1:#16] CONFIRMED low perf | src/Services/OfferManagementService.php:203 | "getApprovedOffers unbounded" pluck+whereIn+get; middleware resolveLink per anl request
- [AUD:B1] ADOPTED medium bug | src/Actions/ApplyToOffer.php:45 | DUP R1:#6 counted once; check-then-create race → 500
- [AUD:B2] ADOPTED medium bug | src/Services/OfferLinkService.php:36 | DUP R1:#7 counted once; arbitrary target_url open redirect
- [AUD:B3] ADOPTED medium bug | src/Models/AffiliateOfferLink.php:157 | raw increment() clicks/conversions; no bot/dedup/idempotency; confirmed
- [AUD:B4] ADOPTED medium bug | src/Models/AffiliateOfferLink.php:60 | counters (clicks/conversions/revenue) + checksum/last_synced_at fillable; confirmed
- [AUD:B5] ADOPTED low perf | src/Services/OfferImportService.php:44 | array_slice(500) loop, no chunk/cursor; confirmed; related R1:#8

---

## affiliates

### E2E findings (verbatim)

End-to-end review: packages/affiliates. 15 findings + positives. No tests/ directory exists in the package.

HIGH
1. [bug] RecordAffiliateConversion.php:179-181 — payload['commission'] bypasses non-negativity and min/max caps. resolveMinorAmount() returns raw (int), so a negative commission is persisted; config commissions.minimum_minor/maximum_minor are only enforced inside CommissionRuleEngine::applyCaps, which the direct CommissionCalculator path and the override path skip. Evidence: `if (isset($payload['commission'])) { return $this->resolveMinorAmount(...); }`. Fix: max(0,…) clamp plus central cap enforcement for all paths. Confidence: high.
2. [bug] Actions/Payouts/CreatePayout.php:36-40 — pays conversions of ANY status and never touches AffiliateBalance. Query filters only whereNull('affiliate_payout_id'), so pending/rejected conversions can be paid; balances diverge from ClaimScheduledPayout flow (which decrements available_minor). Fix: require ApprovedConversion status and decrement/release balance consistently. Confidence: high.
3. [bug] Actions/Payouts/UpdatePayoutStatus.php:34-80 — arbitrary status transitions with no conversion/balance sync. Any→any allowed (completed→pending, failed→completed without payment); completing never marks conversions PaidConversion; cancelling/failing never unlinks affiliate_payout_id, stranding conversions (excluded from future payouts forever). Fix: transition map + sync conversions (paid_at / unlink) + balance compensation. Confidence: high.

MEDIUM
4. [bug] Actions/Conversions/ApplyConversionAccounting.php:74-99 — Approved→Rejected leaks balance. Only Pending/Qualified→Approved/Rejected handled; rejecting an approved conversion never decrements available_minor/lifetime_earnings_minor. Fix: add Approved→Rejected (and Approved→Paid reversal) branches. Confidence: high.
5. [bug] Models/Affiliate.php:393-404 — deleting hook mass-deletes attributions so AffiliateAttribution::deleting never fires; touchpoints orphaned (relation ->delete() skips model events; the conversions null-update is moot since conversions are deleted next, but touchpoints survive). Fix: explicit touchpoint cleanup or chunked each->delete(). Confidence: high.
6. [security] Http/Controllers/AffiliateApiController.php:42-90 — links endpoint has no validation. url defaults to url('/'), ttl unbounded, subject_* unbounded; generator's allowed_hosts defaults to empty (= allow any host), enabling tracked redirect links to arbitrary hosts (phishing via trusted domain). Fix: FormRequest (http/https url, max lengths, ttl range) and document/require allowed_hosts. Confidence: high.
7. [bug] Services/FraudDetectionService.php — fraud pipeline is dead code in-package: zero callers of analyzeClick/analyzeConversion outside the contract/registration (TrackAffiliateVisit and RecordAffiliateConversion implement their own ad-hoc checks and never invoke the service/rules). Signals are never created. Fix: wire service into visit/conversion paths or remove. Confidence: high (host app could call it, but package paths don't).
8. [performance] Services/AffiliateReportService.php:22-25,108-115,234-238 — getSummary/getTrafficSources/affiliateSummary load unbounded conversion collections via ->get() then sum in PHP. Fix: SQL aggregates (SUM/COUNT). Confidence: high.
9. [performance] Services/DailyAggregationService.php:35-88,133-155 — 5 queries per affiliate inside chunk loop; whereDate(col) defeats indexes; updateOrCreate races on unique key under concurrency. Fix: grouped aggregate queries, whereBetween, upsert(). Confidence: high.
10. [bug] Actions/Conversions/ProcessConversionMaturity.php:27-33 — unbounded ->get() of all matured qualified conversions. Fix: chunkById. Confidence: high.
11. [bug] Services/Commissions/CommissionRuleEngine.php:31,87-109 — singleton with context-keyed $rulesCache persists across Octane requests: stale rules after merchant edits (only ProgramCatalogService clears) and unbounded key growth. Fix: scoped binding, TTL, or clear on rule save / Octane tick. Confidence: med.
12. [security] Models Affiliate/AffiliateConversion/AffiliatePayout/AffiliateAttribution $fillable include owner_type/owner_id — any host passing request data to create/update can spoof tenancy. Internal actions set owner explicitly, but fillable keys violate least privilege. Fix: remove from fillable, use forceFill internally. Confidence: med.

LOW
13. [bug/security] Support/Links/AffiliateLinkGenerator.php:36-52 — verify() TypeErrors on array query input (?aff_sig[]=x passes truthy check into hash_equals(array,…)). No in-package callers, but public API DoS. Also signature covers only url+aff+exp; extra params are mutable. Fix: is_string guard. Confidence: high.
14. [security] Console/Commands/ExportAffiliatePayoutCommand.php:22-23 — {payout} interpolated into storage path (traversal outside payouts/); --path allows arbitrary write. Console-only, trusted operator. Fix: basename() sanitize. Confidence: high.
15. [bug] Actions/Affiliates/CreateAffiliate.php:37-93 — validation gaps: $data['name'] unchecked (Error if missing), negative commission_rate accepted, parent_affiliate_id existence/owner never validated (cross-tenant parent link), caller-supplied status bypasses approval mode. Fix: validate + OwnerWriteGuard for parent. Confidence: med.
Also low: cookie secure=null default (config/affiliates.php:132); rate-limit increment-then-put race (TrackAffiliateVisit.php:207-215); prune pluck unbounded + null last_seen_at ordering (AttachAffiliateToCart.php:260-301); Schema::hasTable per conversion (ApplyConversionAccounting.php:118-121); MatureConversion occurred_at assumed non-null (MatureConversion.php:30).

POSITIVES (verified): no DB FK constraints/cascades (foreignUuid without constrained), uuid PKs everywhere, no SoftDeletes; owner scoping via HasOwner + transitive ScopesByAffiliateOwner/ScopesByProgramOwner + OwnerBatchRunner in all 5 commands; DB::table analytics queries apply OwnerQuery; webhook SSRF guarded (PublicHttpUrlGuard + PinnedHttpClient) with HMAC signatures and claimed-lease delivery; payout-method details encrypted; conversion idempotency_key unique + createOrFirst; atomic scheduled-payout claims (lockForUpdate, FIFO allocation); request-scoped (Octane-safe) affiliate lookup cache; EnsureApiAuthorized uses hash_equals known-first; public referral destinations are config-keyed (no open redirect); blade banner escapes output.

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### affiliates
Bugs:
- `CreatePayout:36-100` CRITICAL — read-no-lock `get()` + create payout + conditional claim → overlapping ids paid twice.
- `UpdatePayoutStatus:40-58` HIGH — cancel/fail never refunds `available_minor` (refund lives in `PayoutReconciliationService:95-125`, never called here).
- `MatureConversion:26-48` HIGH — no txn/lock; concurrent matures double-release.
- `ApplyConversionAccounting:90-93` HIGH — unclamped `decrement(holding/lifetime)` → negative.
- `RecordAffiliateConversion:41-171` MEDIUM — no txn; idempotent only with `external_reference`.
- `ApplyConversionAccounting:36-49` MEDIUM — locks Affiliate row, mutates Balance lock-free → TOCTOU.
- `CommissionCalculator:12-22` + `RecordAffiliateConversion:179-181` LOW/HIGH — no rate cap; `payload.commission` trusted.
Security:
- `CreatePayout:65-66` HIGH — `$attributes['owner_*'] ?? conversion->owner_*` with no vs-context validation → re-home payout.
- Unsigned webhook MEDIUM — `WebhookDispatcher:32-33` null signature if secret empty; still queues/sends.
- Attribution cookie bearer MEDIUM — no session/cart fingerprint by default (opt-in `:234-260`).
- API `none` auth LOW — `EnsureApiAuthorized:15-18` disables auth via config; ensure never prod.
- Raw IP/UA PII LOW — persist without hashing/retention note.
Performance:
- `CommissionRuleEngine:$rulesCache` Octane-stale HIGH-verify — `private array` on `singleton(:109)`; verify binding scope (if `scoped`/request, downgrade).
- Report fan-out MEDIUM — per-slice queries; tier/promotion N+1; tenant-aware cache or eager-load.
Good: `ClaimScheduledPayout:62-168` reference impl (locks+FIFO+`operation_key` unique); referral redirect allowlisted.

### Prior-audit fix-first rows
| 2 | affiliates | `Actions/Payouts/CreatePayout.php:36-100` | Double-spend race: read-no-lock → create payout → conditional claim | CRITICAL |
| 13 | affiliates | `Actions/Conversions/MatureConversion.php:26-48` | Lock-free, double-release (approved_at part removed — auto-set) | HIGH |
| 14 | affiliates | `ApplyConversionAccounting::reject:90-93` | Can drive `holding_minor` negative | HIGH |
| 15 | affiliates | `ClaimScheduledPayout` vs `UpdatePayoutStatus:40-58` | Cancelled/failed payouts never refund balance | HIGH |

### Verification verdicts

- [R1:#1] CONFIRMED high bug | src/Actions/Conversions/RecordAffiliateConversion.php:179 | "payload commission bypasses" raw (int) return; negative persists; min/max caps skipped off engine path
- [R1:#2] CONFIRMED high bug | src/Actions/Payouts/CreatePayout.php:36 | "pays conversions ANY" only whereNull filter; any status paid; AffiliateBalance untouched; related AUD:B1
- [R1:#3] CONFIRMED high bug | src/Actions/Payouts/UpdatePayoutStatus.php:34 | "arbitrary status transitions" any→any; conversions/balance never synced; DUP AUD:B2 sev kept
- [R1:#4] CONFIRMED medium bug | src/Actions/Conversions/ApplyConversionAccounting.php:74 | "Approved Rejected leaks" only Pending/Qualified prev handled; Approved→Rejected leaks balance
- [R1:#5] CONFIRMED medium bug | src/Models/Affiliate.php:393 | "deleting hook mass-deletes" attributions mass-delete skips ::deleting (:217); touchpoints orphaned
- [R1:#6] CONFIRMED medium sec | src/Http/Controllers/AffiliateApiController.php:42 | "links endpoint validation" url/ttl/subject_* unvalidated; empty allowed_hosts permits any host
- [R1:#7] CONFIRMED medium bug | src/Services/FraudDetectionService.php:37 | "fraud pipeline dead" zero in-package callers; visit/conversion paths use ad-hoc checks
- [R1:#8] CONFIRMED medium perf | src/Services/AffiliateReportService.php:22 | "Unbounded conversion collections" ->get() then PHP sums in getSummary/TrafficSources/affiliateSummary
- [R1:#9] CONFIRMED medium perf | src/Services/DailyAggregationService.php:35 | "queries per affiliate" ~5 queries/affiliate; whereDate defeats indexes; updateOrCreate races
- [R1:#10] CONFIRMED medium bug | src/Actions/Conversions/ProcessConversionMaturity.php:27 | "unbounded get matured" all matured qualified conversions via ->get(); needs chunkById
- [R1:#11] CONFIRMED high bug | src/Services/Commissions/CommissionRuleEngine.php:31 | "context-keyed rulesCache persists" singleton (:109 provider) + array cache; adopt audit HIGH; DUP AUD:B13
- [R1:#12] CONFIRMED medium sec | src/Models/Affiliate.php:108 | "owner fillable spoof" owner_type/id fillable on Affiliate/Conversion/Payout/Attribution models
- [R1:#13] CONFIRMED low bug | src/Support/Links/AffiliateLinkGenerator.php:36 | "verify TypeErrors array" array aff_sig passes truthy check into hash_equals; extra params mutable
- [R1:#14] CONFIRMED low sec | src/Console/Commands/ExportAffiliatePayoutCommand.php:22 | "payout interpolated traversal" {payout} in storage path; --path arbitrary write; console-only
- [R1:#15] CONFIRMED low bug | src/Actions/Affiliates/CreateAffiliate.php:37 | "validation gaps name" name unchecked; negative rate; parent existence/owner unvalidated; status bypass
- [R1:#16] CONFIRMED low sec | config/affiliates.php:132 | "cookie secure null" secure defaults null via env; tracking cookies not Secure by default
- [R1:#17] CONFIRMED low bug | src/Actions/Affiliates/TrackAffiliateVisit.php:207 | "rate-limit increment-then-put" increment-then-put TTL race; concurrent first-hits slip window
- [R1:#18] CONFIRMED low perf | src/Actions/Affiliates/AttachAffiliateToCart.php:260 | "prune pluck unbounded" unbounded pluck(:288) + null-sensitive last_seen_at ordering
- [R1:#19] CONFIRMED low perf | src/Actions/Conversions/ApplyConversionAccounting.php:118 | "Schema hasTable conversion" hasTable info-schema hit on every conversion handle
- [R1:#20] CONFIRMED low bug | src/Actions/Conversions/MatureConversion.php:31 | "occurred_at assumed non-null" null occurred_at → Error on addDays; kept per FALSE-list
- [AUD:B1] ADOPTED critical bug | src/Actions/Payouts/CreatePayout.php:36 | read-no-lock get + create + conditional claim → double-spend; related R1:#2, distinct claim
- [AUD:B2] ADOPTED high bug | src/Actions/Payouts/UpdatePayoutStatus.php:40 | DUP R1:#3 counted once; cancel/fail never refunds available_minor
- [AUD:B3] ADOPTED high bug | src/Actions/Conversions/MatureConversion.php:25 | no txn/lock; concurrent matures double-release; confirmed; lock-free kept per FALSE-list
- [AUD:B4] ADOPTED high bug | src/Actions/Conversions/ApplyConversionAccounting.php:90 | unclamped decrement(holding/lifetime) → negative; confirmed
- [AUD:B5] ADOPTED medium bug | src/Actions/Conversions/RecordAffiliateConversion.php:41 | no txn; idempotent only with external_reference (createOrFirst :138); confirmed
- [AUD:B6] ADOPTED medium bug | src/Actions/Conversions/ApplyConversionAccounting.php:36 | Affiliate lockForUpdate but Balance mutated lock-free → TOCTOU; confirmed
- [AUD:B7] ADOPTED high bug | src/Services/CommissionCalculator.php:12 | DUP R1:#1 counted once; max(0) only, no rate cap; payload.commission trusted
- [AUD:B8] ADOPTED high sec | src/Actions/Payouts/CreatePayout.php:65 | $attributes owner_* unvalidated vs context → payout re-home; confirmed
- [AUD:B9] ADOPTED medium sec | src/Support/Webhooks/WebhookDispatcher.php:32 | null signature when secret empty; still queues/sends; confirmed
- [AUD:B10] ADOPTED medium sec | src/Actions/Affiliates/TrackAffiliateVisit.php:236 | fingerprint disabled by default (:238); Bearer [REDACTED] attribution cookie; light-confirmed
- [AUD:B11] ADOPTED low sec | src/Support/Middleware/EnsureApiAuthorized.php:15 | 'none' mode disables API auth via config; confirmed (path under Support/)
- [AUD:B12] ADOPTED low sec | src/Services/DailyAggregationService.php:40 | raw ip persisted (ip_address queried); no hashing/retention note; light-confirmed
- [AUD:B13] ADOPTED high bug | src/AffiliatesServiceProvider.php:109 | DUP R1:#11 counted once; singleton binding verified, HIGH stands
- [AUD:B14] ADOPTED medium perf | src/Services/AffiliateReportService.php:20 | per-slice queries + tier/promotion N+1; confirmed shape; related R1:#8
- [AUD:Q#2] ADOPTED critical bug | src/Actions/Payouts/CreatePayout.php:36 | DUP AUD:B1 counted once; double-spend race fix-first
- [AUD:Q#13] ADOPTED high bug | src/Actions/Conversions/MatureConversion.php:25 | DUP AUD:B3 counted once; lock-free double-release
- [AUD:Q#14] ADOPTED high bug | src/Actions/Conversions/ApplyConversionAccounting.php:90 | DUP AUD:B4 counted once; negative holding
- [AUD:Q#15] ADOPTED high bug | src/Actions/Payouts/UpdatePayoutStatus.php:40 | DUP AUD:B2 counted once; never refunds balance

---

## authz

### E2E findings (verbatim)

Authz package review — 3 high, 9 medium, 9 low findings. Rule compliance: OK (uuid PKs, no FK/cascades, no SoftDeletes, no HasOwner by design per CONTEXT — uses Spatie teams + AuthzScope; no Filament/jobs/widgets/routes in package; money N/A).

HIGH
1. [high/bug] `src/AuthzServiceProvider.php:139` — registerTeamResolver() container binding is inert; Spatie news the resolver from `permission.team_resolver` config, which authz docs never mention. Evidence: provider registers `PermissionsTeamResolver::class` singleton, but vendor `PermissionRegistrar::__construct` does `new (config('permission.team_resolver', DefaultTeamResolver::class))`. Impact: a doc-following host gets DefaultTeamResolver, so `setPermissionsTeamId($teamModel)` stores the TEAM's key instead of the AuthzScope id (AuthzScopeResolver auto-create never runs) → team-scoped role lookups silently miss. Troubleshooting doc "Scope Is Always Null" omits team_resolver. Recommend: set `permission.team_resolver` in configureSpatiePermissions (or document as required install step) and remove/keep binding consistently. Confidence: high.
2. [high/performance] `src/AuthzServiceProvider.php:100` + `src/Support/UserRoleChecker.php:27` — uncached super-admin Gate::before on EVERY ability check: each `can()` unsets `roles` relation, flips team id to null, runs hasRole DB query, restores state. No per-request memoization (unlike WildcardPermissionCache). Recommend: memoize verdict per user+team in the scoped WildcardPermissionCache (or own scoped cache). Confidence: high.
3. [high/performance] `src/Authz.php:61` — withScope/withoutTeams/userCanInScope call `forgetCachedPermissions()` (global shared-cache flush, e.g. Redis) twice per invocation, plus relation unsets. In loops (per-row scope checks) this causes cache stampedes and cross-tenant noisy-neighbor invalidation. Spatie team scoping is applied at query time, so the global flush is likely unnecessary — relation unset should suffice. Recommend: drop global flush (verify with test), keep relation unset; fixes nested-use too. Confidence: high on mechanism, med that flush is fully redundant.

MEDIUM
4. [medium/security] `src/Services/ImpersonateManager.php:98` — take() enforces zero authorization (no canImpersonate/canBeImpersonated/scope check); all in-repo callers (filament-authz ImpersonateController/TableAction/ImpersonateAction) check first, but any future/custom caller gets silent privilege escalation. Recommend enforce-by-default inside take() with an explicit escape hatch. Confidence: high.
5. [medium/bug] `src/Models/Role.php:65` — create() override drops parent's `enum_value()` and RoleAlreadyExists duplicate check → BackedEnum names stored unconverted; duplicates raise raw QueryException instead of domain exception. Parent vendor Role.php:56-80 does both. Recommend: mirror parent (enum_value + findByParam check). Confidence: high.
6. [medium/bug] `src/Models/Permission.php:41` — create()/findOrCreate() overrides drop parent's `enum_value()` → BackedEnum names miss cache lookup and get inserted as objects. Parent Permission.php:139 converts. Recommend: add enum_value() in both. Confidence: high.
7. [medium/bug] `src/Models/Role.php:35` — permissions() hardcodes `Permission::class` instead of `Config::permissionModel()` → host permission-model customization via config silently ignored. Recommend: use Config::permissionModel() like parent. Confidence: high.
8. [medium/bug] `src/Guard/SessionGuard.php:23` — quietLogin() calls setUser(), and base setUser() fires the `Authenticated` event (vendor SessionGuard ~L1005-1012) and only then resets loggedOut. So impersonation take/leave fire `Authenticated` despite "without firing auth events" contract → audit/activity listeners log impersonation switches as logins. (Same-guard impersonation does work — loggedOut is reset.) Recommend: assign `$this->user/$this->loggedOut` directly instead of setUser(), or fix docblock. Confidence: high.
9. [medium/security] `src/Services/ImpersonateManager.php:279` + `src/Http/Controllers/LeaveImpersonationController.php:27` — sanitizeBackToUrl accepts any `/...` path; `/\evil.com` is treated as `//evil.com` (cross-origin) by WHATWG URL parsers, and control chars aren't rejected → narrow open-redirect bypass via attacker-influenced referer. Recommend: reject `\` and control chars; consider path whitelist. Confidence: med.
10. [medium/bug] `src/AuthzServiceProvider.php:257` — @canBeImpersonated splits expression on commas naively → nested calls/arrays/ternaries generate broken PHP (Blade 500); empty expression → undefined $args[0]. Expression is template-author input, so correctness not security. Recommend: split only on top-level comma or pass expression through and parse at runtime. Confidence: high.
11. [medium/bug] `src/Http/Controllers/LeaveImpersonationController.php:1` — dead/unwired: routes/ is empty and provider never calls loadRoutesFrom; controller also carries no auth middleware, pushing the security contract onto unknown host wiring. Recommend: ship route registration with auth middleware or document required wiring. Confidence: high.
12. [medium/performance+bug] `src/Support/AuthzScopeResolver.php:45` — resolveId(Model) runs firstOrCreate (+possible save) on the read path: write amplification on every setPermissionsTeamId(Model), plus unique-violation race under concurrency (no retry). Recommend: catch unique violation and re-read; consider read-first fast path. Confidence: high.

LOW (bug/security/performance)
13. [low/bug] `src/Console/Commands/SyncAuthzCommand.php:42` — no config validation: non-string permission or int role key → TypeError; null/false perms passed to syncPermissions risk silent detach-all; no transaction → partial sync. Validate + wrap in transaction. Confidence: med.
14. [low/bug] `src/Models/AuthzScope.php:65` — cascade uses its own committed transaction inside `deleting`, before the outer scope delete → if outer delete fails, roles/assignments already gone; DB::table bypasses role model events (cache flushed manually — OK). Consider afterCommit/transactional delete. Confidence: med.
15. [low/bug] `src/helpers.php:72` — self-impersonation guard uses strict `===` on auth identifiers; int-vs-string mismatch bypasses it. Use loose comparison. Confidence: med.
16. [low/bug] `src/Console/Commands/SuperAdminCommand.php:38,137,181,231` — `--panel` option declared but ignored; getNameColumn hardcoded 'name'; LIKE search interpolates unescaped %/_ (bound, so SQLi-safe, but over-matches); createUser check-then-insert race. Confidence: high.
17. [low/bug] `src/Support/CommandProhibitor.php:15` + `src/Console/Concerns/Prohibitable.php:15` — static prohibition state leaks across Pest tests/workers; prohibitDestructiveCommands double-loops register(). Reset in tests; harmless in Octane (config-time). Confidence: high.
18. [low/performance] `database/migrations/2000_01_01_000001_create_authz_scopes_table.php:22` — unique(['scopeable_type','scopeable_id']) plus redundant identical index; drop the second. Confidence: high.
19. [low/bug] migrations `000001:18`, `000002:63,81` — uuid-only pivot/scopeable columns break int-PK hosts though composer/description claim generic Laravel use; UUID-only requirement undocumented. Document or note. Confidence: high.
20. [low/bug] `src/Services/PermissionKeyBuilder.php:51` — unknown `case` config silently falls back to kebab; `src/Models/Permission.php:33` getPermissions() is pure-delegation dead code. Validate case; delete override. Confidence: high.
21. [low/security] `src/AuthzServiceProvider.php:211` — `$auth->extend('session', ...)` replaces the session driver for ALL guards app-wide; host custom session drivers get clobbered depending on provider order. Document. Confidence: med.

PROCESS
22. [medium/correctness] No tests ship with the package (no tests/ dir) for security-critical code (impersonation, Gate hooks, scope resolution). Repo-level tests exist under tests/ but package has zero own coverage. Recommend Pest coverage for take/leave, Gate hooks, resolver, sanitizer. Confidence: high.

POSITIVES (brief): session ID rotation + CSRF regeneration on every identity switch; nested impersonation refused; quiet logout avoids remember-token cycling; AuthzScopeContext/WildcardPermissionCache correctly `scoped` (not singletons) with per-team cache keys; boot-time separator + scopes-require-teams assertions; backTo sanitization present in both take() and leave paths; ImpersonationScopeGuard and SuperAdmin search use bound parameters (no SQLi; only constant selectRaw('1')); scope delete cleans its roles/assignments in a transaction; no DB FKs, uuid PKs, no SoftDeletes per repo rules; no mass-assignment sinks, XSS/SSRF/traversal/deserialization surface; SuperAdmin assigns global role with team=null then restores team in finally.

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### authz
Bugs: `Models/Role.php:65-81` LOW — caller-supplied teams key wins (`! array_key_exists`); harden if ever request-adjacent. (`Permission.php` part removed — no teams code there.)
Security:
- `Services/ImpersonateManager.php:98-132` HIGH — `take()` has zero authorization; relies entirely on callers.
- `Models/Role.php:110-112` LOW — `findByParam` loops raw `$params` into `where($key,$value)`; allowlist if ever exposed (currently `protected static`).
Performance: clean. `AuthzScope deleting` 5× `DB::table` in one txn; volume bounded.

### Prior-audit fix-first rows
| — | authz | `Services/ImpersonateManager.php:98-132` | `take()` performs no authorization | HIGH |

### Verification verdicts

- [R1:#1] CONFIRMED high bug | src/AuthzServiceProvider.php:139 | "registerTeamResolver container binding" inert; vendor PermissionRegistrar:60 news from permission.team_resolver config
- [R1:#2] CONFIRMED high perf | src/AuthzServiceProvider.php:100 | "uncached super-admin Gate" every can() unsets relations + hasRole query; no per-request memo
- [R1:#3] CONFIRMED high perf | src/Authz.php:61 | "withoutTeams userCanInScope call" forgetCachedPermissions twice per call; stampede risk; redundancy needs runtime test
- [R1:#4] CONFIRMED high sec | src/Services/ImpersonateManager.php:98 | "take enforces zero" take() has no authorization; adopt audit HIGH; DUP AUD:B2
- [R1:#5] CONFIRMED medium bug | src/Models/Role.php:65 | "create drops parent" no enum_value + no RoleAlreadyExists; vendor Role:56-80 does both
- [R1:#6] CONFIRMED medium bug | src/Models/Permission.php:41 | "create findOrCreate overrides" no enum_value; BackedEnum mishandled; vendor converts (:56,:141)
- [R1:#7] CONFIRMED medium bug | src/Models/Role.php:35 | "permissions hardcodes Permission" ignores Config::permissionModel(); vendor Role:90 uses Config
- [R1:#8] CONFIRMED medium bug | src/Guard/SessionGuard.php:23 | "quietLogin calls setUser" base setUser fires Authenticated (vendor :1012); breaks quiet contract
- [R1:#9] CONFIRMED medium sec | src/Services/ImpersonateManager.php:279 | "sanitizeBackToUrl accepts slash" /\evil.com passes; no backslash/control rejection (both copies)
- [R1:#10] CONFIRMED medium bug | src/AuthzServiceProvider.php:257 | "canBeImpersonated splits expression" naive comma split; nested exprs break; empty → undefined $args[0]
- [R1:#11] CONFIRMED medium bug | src/Http/Controllers/LeaveImpersonationController.php:1 | "dead unwired routes" routes/ empty; no loadRoutesFrom; no auth middleware
- [R1:#12] CONFIRMED medium perf | src/Support/AuthzScopeResolver.php:45 | "resolveId runs firstOrCreate" write (+save) on read path; unique race without retry
- [R1:#13] CONFIRMED low bug | src/Console/Commands/SyncAuthzCommand.php:42 | "no config validation" bad types → TypeError; null perms risk detach-all; no txn
- [R1:#14] CONFIRMED low bug | src/Models/AuthzScope.php:65 | "cascade own committed" inner txn commits before outer delete; DB::table skips model events
- [R1:#15] CONFIRMED low bug | src/helpers.php:72 | "self-impersonation guard strict" === on auth identifiers; int/string mismatch bypasses guard
- [R1:#16] CONFIRMED low bug | src/Console/Commands/SuperAdminCommand.php:38 | "panel option ignored" --panel unused; name hardcoded; LIKE unescaped; check-then-insert race
- [R1:#17] CONFIRMED low bug | src/Support/CommandProhibitor.php:15 | "static prohibition state" statics leak across tests; prohibitDestructiveCommands double-loops
- [R1:#18] CONFIRMED low perf | database/migrations/2000_01_01_000001:22 | "unique plus redundant" identical unique + index on (type,id); drop second
- [R1:#19] CONFIRMED low bug | database/migrations/2000_01_01_000001:18 | "uuid-only pivot scopeable" uuid scopeable/pivot keys break int-PK hosts; undocumented
- [R1:#20] CONFIRMED low bug | src/Services/PermissionKeyBuilder.php:51 | "unknown case config" silent kebab fallback; Permission::getPermissions pure delegation
- [R1:#21] CONFIRMED low sec | src/AuthzServiceProvider.php:211 | "extend session replaces" replaces session driver for ALL guards app-wide; document
- [R1:#22] CONFIRMED medium correctness | tests/:absent | "No tests ship" no tests/ dir; zero coverage for impersonation/Gate/resolver/sanitizer
- [AUD:B1] ADOPTED low bug | src/Models/Role.php:65 | caller-supplied teams key wins (!array_key_exists :75); harden if request-adjacent; confirmed
- [AUD:B2] ADOPTED high sec | src/Services/ImpersonateManager.php:98 | DUP R1:#4 counted once; take() zero authorization
- [AUD:B3] ADOPTED low bug | src/Models/Role.php:110 | findByParam loops raw $params into where; protected static; allowlist if exposed; confirmed
- [AUD:Q#1] ADOPTED high sec | src/Services/ImpersonateManager.php:98 | DUP AUD:B2 counted once; take() no-authz fix-first row

---

## cart

### E2E findings (verbatim)

End-to-end review of packages/cart (domain package: no routes/controllers/views; Filament UI lives in paired filament-cart).

POSITIVES (brief): uuid PKs everywhere; no DB FK constraints/cascades (foreignUuid without constrained()) and no SoftDeletes per repo rules; owner-scoping consistently via commerce-support (HasOwner/OwnerScope/OwnerContext/OwnerWriteGuard/OwnerScopeKey) on CartModel, Condition, CartSnapshot, DatabaseStorage, commands, jobs, and actions; money as int minor units with a single boundary (Support/CartMoney) and basis-point percentages; CAS optimistic locking with version column; input size limits (CartLimits) plus closure/resource serializability rejection; Octane state reset for cart bindings and preset statics; recursion guard for global-condition application via Context; abandonment command has dry-run/max-affected/confirm/all-owners safeguards; no eval/unserialize/SSRF/path-traversal sinks found; JSON decode failures fall back safely with logging.

FINDINGS:

1) severity: high | category: security | packages/cart/src/Support/LoginMigrationIdentifierResolver.php:56 + HandleUserLoginAttempt.php:17
Title: Guest-cart migration keyed by public login identifier allows cart-content injection into a victim's account
Description: On every login Attempting (including failed attempts), the current session id is cached under sha256(lower(email|username|phone)). On Login, whatever session id is cached under the victim's identifier has its cart merged into the victim's account. An attacker who knows the victim's email can start a login attempt from the attacker's own session (caching attackerSession under victimEmail); when the victim logs in within the 5-minute TTL, the attacker's guest items/conditions/metadata are merged into the victim's cart. Item payloads (id/name/price/attributes) are attacker-controlled at the package level via add(), so this is a cross-account integrity violation (price/name confusion at checkout). Cache::pull also makes the first Login consume the entry, breaking multi-guard or retry logins.
Evidence: `Cache::put(LoginMigrationCacheKey::make($identifier), $sessionId, ...)` on Attempting; `Cache::pull(...)` then `migrationAction->execute($user, $instance, $sessionId)` on Login.
Recommendation: Do not key pending migrations by a remotely-supplied identifier. Instead stash a random one-time migration token in the guest's own session pre-login and consume it post-login from that same session (session fixation-safe: read before ID regeneration), or verify the cached session id is not attacker-planted by binding it to a secret stored in the guest session. At minimum, only migrate when the cached session differs from any authenticated session and log the merge.
Confidence: med (injection path is fully in-package; price-impact severity depends on whether the storefront sets prices server-side).

2) severity: medium | category: security | packages/cart/src/CartManager.php:230
Title: CartManager::swap() silently drops owner scope via withOwner(null)
Description: swap() unconditionally calls `$this->storage->withOwner(null)`, so an owner-scoped manager (e.g. `$manager->forOwner($x)->swap(...)`) operates on global-scope carts instead of the owner's carts when cart.owner.enabled=true. Combined with swapIdentifier's destructive target delete, this can read, overwrite, and delete another scope's carts.
Evidence: `$storage = $this->storage->withOwner(null); $swapped = $storage->swapIdentifier(...)`.
Recommendation: Use `$this->storage` as-is in swap() so the current owner scope is preserved; if cross-scope swap is ever needed, require an explicit owner argument plus explicit-global assertion.
Confidence: high.

3) severity: medium | category: bug | packages/cart/src/Storage/DatabaseStorage.php:352
Title: swapIdentifier() unconditionally deletes the target cart (silent data loss)
Description: The swap transaction first deletes any existing cart at the target identifier, then re-points the source row. A swap into an identifier that already has items silently destroys the target cart's items/conditions/metadata with no merge, no event, and no return signal distinguishing it. The pre-transaction has() check is also outside the transaction (TOCTOU).
Evidence: `$this->baseQuery($newIdentifier, $instance)->delete(); ... update(['identifier' => $newIdentifier ...])`.
Recommendation: Refuse or merge on target conflict (return false / throw / reuse merge strategies) instead of deleting; move the existence check inside the transaction with lockForUpdate.
Confidence: high.

4) severity: medium | category: bug | packages/cart/src/Storage/DatabaseStorage.php:710 + Snapshots/NormalizedCartSynchronizer.php:51
Title: Concurrent first-writes race the unique key and surface raw QueryException instead of a domain conflict
Description: performCasUpdate()'s insert path and NormalizedCartSynchronizer::syncFromCart()'s firstOrNew()->save() both assume absence-then-insert. Two concurrent requests creating the same (owner_scope, identifier, instance) cart/snapshot hit the unique index and throw an unhandled QueryException (HTTP 500) rather than CartConflictException or a retry-as-update.
Evidence: `$this->database->table($this->table)->insert($insertData);` with no unique-violation handling; `$cartModel = ...->firstOrNew([...]); ... $cartModel->save();`.
Recommendation: Catch the unique-violation (code 23000) and retry once as a version-checked update, or use upsert/atomic insert-ignore-then-update semantics.
Confidence: high.

5) severity: medium | category: bug | packages/cart/src/Actions/MigrateGuestCartToUserAction.php:67
Title: Guest→user migration is not atomic; partial failure leaves duplicated/diverged carts
Description: execute() performs ~5 separate writes (putItems, putConditions, putMetadataBatch, markSourceCartAsMerged, guest forget) with no encompassing transaction. A failure or CAS conflict midway (e.g. conditions update throws) leaves items merged but the guest cart not deleted, or metadata half-merged, causing duplicate items on retry/login and divergent snapshots.
Evidence: sequential `$this->resolveStorage()->putItems/putConditions/putMetadataBatch(...)`, then `markSourceCartAsMerged(...)`, then `$guestStorage->forget(...)` with no DB::transaction wrapper.
Recommendation: Wrap the merge + source-mark + source-delete in one DB transaction (storage already supports CAS inside transactions) and make the operation idempotent on retry.
Confidence: high.

6) severity: medium | category: bug | packages/cart/src/Actions/MigrateGuestCartToUserAction.php:255
Title: mergeItems() writes raw merged quantities, bypassing max_item_quantity and item validation
Description: Merged quantities are computed by the strategy and written straight to storage via putItems(), which only validates item count and byte size — never per-item quantity. A merge can therefore produce quantities exceeding cart.limits.max_item_quantity (which add()/update() enforce via CartItem validation), and can propagate corrupt shapes (`$existingItem['quantity'] ?? 0` may be non-int).
Evidence: `$mergedItems[$itemId]['quantity'] = $newQuantity; ... putItems($userIdentifier, $instance, $mergedItems);`.
Recommendation: Clamp/validate merged quantities against CartLimits (and re-validate each merged row through CartItem) before persisting; surface capped quantities in the merged event.
Confidence: high.

7) severity: medium | category: bug | packages/cart/src/Actions/ApplyStoredCondition.php:64
Title: applyCustom() has no input validation; missing keys or bad target DSL crash with TypeError/uncaught exception
Description: applyCustom() reads `$data['name']`, `$data['type']`, `$data['target']`, `$data['value']` directly (undefined-key warning + TypeError on missing keys under strict types) and passes raw target strings into ConditionTarget::from(), whose InvalidArgumentException is uncaught (500). There is also no value sanity check (e.g. a custom 100% discount) — authorization is entirely delegated to the caller with no documented contract.
Evidence: `name: (string) $data['name'], type: (string) $data['type'], target: (string) $data['target'], value: $data['value']` with no isset/validation.
Recommendation: Validate required keys/types up front and throw domain Exception (like the sibling finders do); catch/normalize ConditionTarget failures; document the caller-auth contract (Filament policy) or accept an authorizer.
Confidence: high.

8) severity: medium | category: performance | packages/cart/src/Console/Commands/ClearAbandonedCartsCommand.php:278
Title: Snapshot abandonment marking loads all candidates unbounded and saves row-by-row (memory + N+1)
Description: processAbandonedSnapshots() calls ->get() with no chunking (all matching snapshots hydrated at once), then issues one UPDATE plus events per row via markAsAbandoned(). countAbandonedSnapshots() doubles the scan. Large backlogs risk OOM and long table pressure.
Evidence: `$snapshots = $this->abandonedSnapshotsQuery($minutes)->get(); foreach ($snapshots as $snapshot) { $snapshot->markAsAbandoned(); }`.
Recommendation: Process with chunkById()/lazyById (e.g. 500–1000) and a single bulk update for checkout_abandoned_at where per-row events are not required, keeping the max-affected guard.
Confidence: high.

9) severity: medium | category: performance | packages/cart/src/Console/Commands/ClearAbandonedCartsCommand.php:556
Title: Abandoned-cart deletion plucks ALL ids into memory before chunking
Description: processForOwner() does `$query->clone()->pluck('id')->chunk($batchSize)` — pluck() materializes every matching id in PHP memory first; chunk() then only splits the in-memory collection. --batch-size therefore does not bound memory on large carts tables.
Evidence: `steps: $query->clone()->pluck('id')->chunk($batchSize)`.
Recommendation: Use chunkById()/lazyById over the base query (or delete in ranged batches) instead of pluck-then-chunk.
Confidence: high.

10) severity: medium | category: performance | packages/cart/src/Snapshots/SyncCartOnEvent.php:37 + Snapshots/CartSyncManager.php:17
Title: Every cart mutation dispatches a sync job with no coalescing; jobs can overtake and stale-write snapshots
Description: All 10 cart/item/condition events trigger CartSyncManager::sync(), which (with default queue_sync=true) dispatches one SyncNormalizedCartJob per event. Bulk edits (addMultiple, refreshBuyablePrices, condition syncs) flood the cart-sync queue, and concurrent jobs for the same cart run last-writer-wins with no version check — an older job can overwrite a newer snapshot (stale items/totals), or collide on the unique key (see #4).
Evidence: `$dispatcher->listen([...10 events...], SyncCartOnEvent::class)`; `SyncNormalizedCartJob::dispatch(...)` unconditionally; job is not ShouldBeUnique and synchronizer ignores versions.
Recommendation: Make the job ShouldBeUnique (by owner+identifier+instance) or debounce/coalesce (dispatch after response / unique-until-processed), and add a version/updated_at guard so stale jobs no-op.
Confidence: med (flood is certain; stale-overwrite requires concurrent workers).

11) severity: medium | category: performance | packages/cart/src/Traits/ManagesStorage.php:128
Title: Associated-model restoration issues one unscoped Eloquent query per cart item (N+1 + cross-scope read)
Description: getItemsFromStorage() calls restoreAssociatedModel() per item, which runs `$className::find($id)` for any stored class+id pair. A 50-item cart costs 50 extra queries on every getItems()/total/content call, the find() is not owner-scoped, and the class name comes from stored JSON (any Model subclass is instantiated/returned into the cart object graph).
Evidence: `if (isset($associatedData['id']) && is_subclass_of($className, Model::class)) { return $className::find($associatedData['id']); }`.
Recommendation: Batch-resolve (group ids by class, one whereIn per class), apply the current owner scope to the lookup, and consider allow-listing buyable model classes; cache resolved models per request.
Confidence: high.

12) severity: medium | category: bug | packages/cart/src/Traits/ManagesItems.php:311 + Traits/ManagesBuyables.php:168
Title: Bulk paths amplify writes: addMultiple() and refreshBuyablePrices() do one read+write+events+sync-job per item
Description: addMultiple() calls addItemInternal() per row (each: getItems, save, invalidate, 1–2 events → sync jobs), so a 100-item import costs ~100 DB read-modify-writes and ~100+ queued snapshot syncs; a mid-loop validation failure also leaves a partially imported cart. refreshBuyablePrices() similarly calls update() per changed item.
Evidence: `foreach ($items as $item) { $cartItem = $this->addItemInternal(...); }`; `foreach (...) { $this->update($item->id, ['price' => $newPrice]); }`.
Recommendation: Add a batch path that loads once, applies all rows in memory, saves once, and dispatches one sync; wrap in a transaction for all-or-nothing imports.
Confidence: high.

13) severity: medium | category: bug | packages/cart/src/Traits/ManagesItems.php:28 + Traits/ManagesItems.php:82
Title: add()/update() accept malformed quantity shapes and fail with TypeError/arithmetic errors instead of domain exceptions
Description: addMultiple() reads `$item['id']` without isset (missing key → null → TypeError in addItemInternal's string|int param). update() treats `$data['quantity']` as relative delta with no type check: a float/string/array-without-value flows into `setQuantity(int)` (TypeError) or `$item->quantity + $quantity` (unsupported-operand TypeError for non-numeric strings). Callers get 500s instead of InvalidCartItemException.
Evidence: `$item['id'], $item['name'] ?? null, ...` (id not null-safe); `$newQuantity = $item->quantity + $quantity; ... setQuantity($newQuantity)`.
Recommendation: Validate id/quantity shape and numeric-int type at the top of add/update and throw InvalidCartItemException; cast only validated numeric strings.
Confidence: high.

14) severity: low | category: bug | packages/cart/src/Actions/MigrateGuestCartToUserAction.php:184
Title: Swap-path merge attribution never records merged_into_id (mark called before target exists)
Description: In swapIdentifierWithStorage(), markSourceCartAsMerged() runs before the target cart is written. Its target lookup therefore finds nothing and returns early, so guest→user swaps via this path never set merged_into_id — unlike the merge path where the target already exists. Abandonment/audit queries on merged_into_id silently miss these migrations.
Evidence: `$this->markSourceCartAsMerged(...); $targetStorage->putItems(...);` (mark precedes insert).
Recommendation: Mark after writing the target (or pass the created target id through), matching the merge-path ordering.
Confidence: high.

15) severity: low | category: bug | packages/cart/src/Actions/MigrateGuestCartToUserAction.php:303
Title: sumItemQuantities() assumes well-formed item arrays; corrupt storage rows cause TypeError
Description: The reducer is typed `fn (int $sum, array $item)` and reads `$item['quantity'] ?? 0` without numeric checks. A corrupt items payload (non-array row, string quantity) throws TypeError — uncaught on the execute() path (only executeForUser() catches Exception).
Evidence: `array_reduce($items, static fn (int $sum, array $item) => $sum + ($item['quantity'] ?? 0), 0)`.
Recommendation: Guard with is_array/is_numeric checks and cast to int, consistent with the defensive reads elsewhere.
Confidence: high.

16) severity: low | category: security | packages/cart/src/Storage/DatabaseStorage.php:169
Title: flush() in an explicit-global context truncates every owner's carts
Description: When owner-scoped storage is null (explicit global), flush() calls truncate() on the whole carts table, wiping all tenants' carts — not just global-scope rows. Blast radius is limited by the testing/local-environment gate, but a local/dev command or test helper with owner enabled still destroys cross-tenant data.
Evidence: `if ($this->ownerType !== null ...) { ...delete(); } else { $query->truncate(); }`.
Recommendation: Always delete with the owner predicate applied (global-only predicate when global), never truncate; or refuse flush entirely when cart.owner.enabled=true.
Confidence: high.

17) severity: low | category: performance | packages/cart/src/Models/Traits/AssociatedModelTrait.php:31
Title: Full associated-model toArray() is persisted into cart JSON but never used on restore
Description: getAssociatedModelArray() embeds the model's entire toArray() (plus class+id) into every cart item's stored JSON, inflating items payloads toward the 1MB cap and persisting stale (and potentially sensitive, hidden-respecting but still broad) snapshots. restoreAssociatedModel() ignores the embedded data and re-fetches by class+id anyway.
Evidence: `['class' => ..., 'id' => ..., 'data' => ...->toArray()]` persisted; restore uses only `['class','id']`.
Recommendation: Persist only class+id (+optional display snapshot fields), or drop the data key.
Confidence: high.

18) severity: low | category: bug | packages/cart/src/Storage/DatabaseStorage.php:547 + Models/Condition.php:733
Title: Unbounded recursion in serializability/context normalization runs before size checks (deep-nesting DoS)
Description: validateSerializable() recurses without a depth cap and is called before the byte-size check, so a deeply nested but small payload can exhaust the stack. Condition::normalizeContextValue() has the same shape and additionally splits any comma-containing string into arrays and JSON-decodes bracket strings, which can surprise legitimate values ('a,b' becomes ['a','b']).
Evidence: `validateSerializable($value, $type, $currentPath)` recursive with no depth param; `normalizeContextValue()` recursive + `explode(',', $trimmed)`.
Recommendation: Add a max-depth constant (e.g. 32) to both; make comma-splitting opt-in per key rather than implicit.
Confidence: med.

19) severity: low | category: bug | packages/cart/src/Models/CartItem.php:186 + Traits/ManagesItems.php:413
Title: Price string normalization strips commas, making '1,000' ambiguous (1000 minor units, not 1000 major)
Description: ',' is stripped before numeric parsing, so '1,000' becomes 1000 minor units (RM 10.00) while '10.50' is treated as major→minor (1050). Thousand-separated major-unit input is silently interpreted 100× too small.
Evidence: `str_replace([... ',', ' '], '', $normalized)` then `str_contains($normalized, '.') ? minorFromDecimal : (int)`.
Recommendation: Document that comma input is unsupported and reject it, or parse thousand separators as major units consistently.
Confidence: med.

20) severity: low | category: bug | packages/cart/src/Snapshots/CartSnapshot.php:279 + Listeners/HandleUserLogin.php:54
Title: Minor hardening gaps: unscoped user() relation; login listener assumes web session
Description: (a) CartSnapshot::user() belongsTo(identifier→id) ignores owner scope and mis-resolves guest session-string identifiers. (b) HandleUserLogin calls session()->flash() unconditionally; in console/queue-authenticated Login events the session store may be unavailable, and it iterates an unbounded getInstances() list per login.
Evidence: `belongsTo($userModel, 'identifier', 'id')`; `session()->flash('cart_migration', ...)`; `$guestStorage->getInstances($sessionId)` loop.
Recommendation: Scope or remove user(); guard session access with app()->bound('session')/runningInConsole checks; cap instances processed per login.
Confidence: med.

NOTED AS UNRESOLVED/OUT-OF-SCOPE: No XSS/SSRF/traversal/deserialization sinks in this package (rendering lives in filament-cart; stored names/attributes are intentionally unescaped — consumers must escape on output). No package-level Pest tests exist to cross-check behavior; full-suite verification was out of scope for this review task.

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### cart
Bugs:
- `DatabaseStorage:683-724` HIGH — CAS insert `first()` + bare `insert()`, no 23000 retry on `(owner_scope,identifier,instance)` unique.
- `MigrateGuestCartToUserAction:107-125,184-214` HIGH — putItems/putConditions/putMetadata + markMerged + forget, no txn → duplicates → double-checkout.
- `CartModel:161-165` MEDIUM — `markAsConverted()` no version/lock, no already-converted guard.
- `Condition:227,404-411` MEDIUM (was HIGH "undocumented") — `"+5"`=5¢ vs `"+5.00"`=500¢ IS in docblock `:399-403`; unit-cliff concern, not undocumented.
- `ApplyStoredCondition:64-118` MEDIUM-HIGH (was HIGH) — `applyCustom()` no allowlist/range on `name/type/target/value/order` (rules `factory_keys` allowlisted `:78-81`).
- `DatabaseStorage:352-373` MEDIUM — `swapIdentifier()` unconditional target wipe; `has()` outside txn.
Security:
- `NormalizedCartSynchronizer:135-210` pattern note LOW (was MEDIUM exploit) — child `where(cart_id)->delete()/upsert()` unscoped, but parent resolved `forOwner(:51)` + `deleteNormalizedCart` re-scopes `:126-163`; not exploitable as stated. Harden with explicit scope.
- Abandoned-cart raw deletes safe only via owner iteration — `ClearAbandonedCartsCommand:90-94,311+` iterates tuples + `withOwner` per batch.
Performance:
- 3+ round-trips per cart MEDIUM (narrowed) — true for snapshot path (`save` + items `upsert:210` + conditions `upsert:277`); primary `carts` storage single-row JSON.
- CAS spin no backoff MEDIUM — `handleCasConflict:732-745` throws, no retry.
- DONE (2026-09-13, §8 item 8) — Missing `(identifier,instance,version)` composite LOW. Fixed: kept additive (`2026_09_12_162447_add_cas_lookup_index_to_carts_table.php`).

### Prior-audit fix-first rows
| 34 | cart | `Storage/DatabaseStorage.php:683-724` | CAS insert race, no duplicate-key retry | HIGH |
| 35 | cart | `Actions/MigrateGuestCartToUserAction.php:107-125,184-214` | Non-atomic migration → duplicated carts | HIGH |
| — | cart | `CheckoutService:51-71` gap | No owner assertion on `startCheckout` (MEDIUM, kept near queue) | MEDIUM |

### Migration-batch rows (§8, code may already be fixed)
| 8 | cart `(identifier, instance, version)` composite (§4) | Kept additive (`2026_09_12_162447_*`) | `packages/cart/docs/08-storage.md` |

### Verification verdicts

- [R1:#1] CONFIRMED high security | cart/src/Support/LoginMigrationIdentifierResolver.php:56 | "Guest-cart migration keyed": Attempting caches session under identifier; Login pulls it and merges. Price impact med.
- [R1:#2] CONFIRMED medium security | cart/src/CartManager.php:230 | "CartManager::swap() silently drops": swap() forces withOwner(null), reads/writes/deletes cross-scope.
- [R1:#3] CONFIRMED medium bug | cart/src/Storage/DatabaseStorage.php:352 | "swapIdentifier() unconditionally deletes": target deleted pre-swap, no merge/signal; DUP AUD:B6.
- [R1:#4] CONFIRMED high bug | cart/src/Storage/DatabaseStorage.php:710 | "Concurrent first-writes race": no 23000 rescue on insert path; raw QueryException 500; DUP AUD:B1,Q#1 adopted HIGH.
- [R1:#5] CONFIRMED high bug | cart/src/Actions/MigrateGuestCartToUserAction.php:67 | "Guest→user migration is": 5 separate writes, no txn; partial failure duplicates; DUP AUD:B2,Q#2 adopted HIGH.
- [R1:#6] CONFIRMED medium bug | cart/src/Actions/MigrateGuestCartToUserAction.php:255 | "mergeItems() writes raw": merged qty skips max_item_quantity + CartItem validation (putItems checks count/size only).
- [R1:#7] CONFIRMED med-high bug | cart/src/Actions/ApplyStoredCondition.php:64 | "applyCustom() has no": raw $data keys, uncaught ConditionTarget; DUP AUD:B5 adopted med-high.
- [R1:#8] CONFIRMED medium perf | cart/src/Console/Commands/ClearAbandonedCartsCommand.php:278 | "Snapshot abandonment marking": unbounded get() + per-row save; count doubles scan.
- [R1:#9] CONFIRMED medium perf | cart/src/Console/Commands/ClearAbandonedCartsCommand.php:556 | "Abandoned-cart deletion plucks": pluck-all-then-chunk; --batch-size does not bound memory.
- [R1:#10] CONFIRMED medium perf | cart/src/Snapshots/SyncCartOnEvent.php:37 | "Every cart mutation": 1 dispatch/event, job not ShouldBeUnique, no version guard; overtake possible.
- [R1:#11] CONFIRMED medium perf | cart/src/Traits/ManagesStorage.php:128 | "Associated-model restoration issues": per-item unscoped ::find on stored class+id; N+1.
- [R1:#12] CONFIRMED medium bug | cart/src/Traits/ManagesItems.php:311 | "Bulk paths amplify": per-item read+write+events+jobs; mid-loop failure = partial import.
- [R1:#13] CONFIRMED medium bug | cart/src/Traits/ManagesItems.php:28 | "add()/update() accept malformed": missing id key; untyped qty delta flows to arithmetic.
- [R1:#14] CONFIRMED low bug | cart/src/Actions/MigrateGuestCartToUserAction.php:184 | "Swap-path merge attribution": mark-before-target-write; merged_into_id never set on swap path.
- [R1:#15] CONFIRMED low bug | cart/src/Actions/MigrateGuestCartToUserAction.php:303 | "sumItemQuantities() assumes well-formed": typed reducer + unguarded qty; corrupt rows TypeError.
- [R1:#16] CONFIRMED low security | cart/src/Storage/DatabaseStorage.php:169 | "flush() in an": explicit-global flush() truncate()s all tenants; testing/local gate only.
- [R1:#17] CONFIRMED low perf | cart/src/Models/Traits/AssociatedModelTrait.php:37 | "Full associated-model toArray()": full toArray persisted; restore uses class+id only; bloat to 1MB cap.
- [R1:#18] CONFIRMED low bug | cart/src/Storage/DatabaseStorage.php:547 | "Unbounded recursion in": validateSerializable no depth cap pre-size-check; comma-split surprises.
- [R1:#19] CONFIRMED low bug | cart/src/Models/CartItem.php:186 | "Price string normalization": commas stripped so '1,000' = 1000 minor (100x ambiguity vs decimals).
- [R1:#20] CONFIRMED low bug | cart/src/Snapshots/CartSnapshot.php:279 | "Minor hardening gaps:": user() unscoped, mis-resolves guests; flash() assumes web session.
- [AUD:B1] ADOPTED high bug | cart/src/Storage/DatabaseStorage.php:683 | DUP R1:#4; CAS insert race, no duplicate-key retry. Counted once.
- [AUD:B2] ADOPTED high bug | cart/src/Actions/MigrateGuestCartToUserAction.php:107 | DUP R1:#5; non-atomic migration, duplicates. Counted once.
- [AUD:B3] ADOPTED medium bug | cart/src/Models/CartModel.php:161 | markAsConverted: no version/lock/already-converted guard; light confirm.
- [AUD:B4] ADOPTED medium bug | cart/src/Models/Condition.php:399 | "+5" syntax documented in docblock; residual unit-cliff concern stands.
- [AUD:B5] ADOPTED med-high bug | cart/src/Actions/ApplyStoredCondition.php:64 | DUP R1:#7; no allowlist/range on custom fields. Counted once.
- [AUD:B6] ADOPTED medium bug | cart/src/Storage/DatabaseStorage.php:352 | DUP R1:#3; unconditional target wipe. Counted once.
- [AUD:B7] ADOPTED low security | cart/src/Snapshots/NormalizedCartSynchronizer.php:135 | child unscoped but parent forOwner + re-scope; harden only.
- [AUD:B8] ADOPTED low security | cart/src/Console/Commands/ClearAbandonedCartsCommand.php:90 | raw deletes safe via owner-tuple iteration; no action.
- [AUD:B9] ADOPTED medium perf | cart/src/Snapshots/NormalizedCartSynchronizer.php:92 | snapshot path: save + items/conditions upserts; primary single-row.
- [AUD:B10] ADOPTED medium perf | cart/src/Storage/DatabaseStorage.php:732 | handleCasConflict throws, no retry/backoff.
- [AUD:B11] FIXED low perf | cart/database/migrations/2000_02_01_000001:38 | (identifier,instance,version) index present in base migration; no 2026 file.
- [AUD:Q#1] ADOPTED high bug | cart/src/Storage/DatabaseStorage.php:683 | DUP R1:#4; queue row 34. Counted once.
- [AUD:Q#2] ADOPTED high bug | cart/src/Actions/MigrateGuestCartToUserAction.php:107 | DUP R1:#5; queue row 35. Counted once.
- [AUD:Q#4] ADOPTED low perf | cart/database/migrations/2000_02_01_000001:38 | DUP AUD:B11; migration-batch row 8 consolidated. Counted once.

---

## cashier

### E2E findings (verbatim)

E2E review: packages/cashier (multi-gateway billing abstraction, no own models/tables)

CRITICAL
1. [critical/security] src/Actions/SyncWebhook.php:22 + src/Gateways/StripeGateway.php:413 + src/Gateways/ChipGateway.php:444 — Webhooks handled without any signature verification. SyncWebhook dispatches WebhookReceived then calls handleWebhook directly. Stripe path builds a synthetic Request via `Request::create(...json_encode($payload))` and calls `app(WebhookController::class)->handleWebhook($request)` — this bypasses laravel/cashier's VerifyWebhookSignature middleware (registered only in the controller constructor; verified in vendor), so forged payloads are processed; re-encoding also means no signature could ever match. CHIP path ignores $headers entirely and never calls verifyWebhookSignature. Impact: forged subscription/payment state changes; downstream cart destruction + affiliate payouts (see #7 chain). Fix: call verifyWebhookSignature on the RAW request body before dispatching WebhookReceived, and route real webhooks through the packages' HTTP controllers/middleware instead of SyncWebhook; make SyncWebhook verify when headers+raw payload supplied. Confidence: high.

2. [critical/bug] src/Checkout/CartCheckoutBuilder.php:285 + src/Gateways/Chip/ChipCheckoutBuilder.php:93 — Cart→CHIP checkout creates RM0.00 products. CartCheckoutBuilder calls `$builder->price($id,$qty)`; the CHIP builder implements price() as `['name'=>$price,'quantity'=>$qty,'price'=>0]` ("Should be set via product()"). PurchasesApi validation only rejects negative prices (verified), so cart checkouts post zero-amount purchases. Fix: implement price()→product mapping with real amounts (pass unit amount + currency through CheckoutBuilderContract or branch on gateway), or throw unsupported instead of silently charging 0. Confidence: high.

HIGH
3. [high/security] src/Gateways/Chip/ChipPayment.php:439 + src/Gateways/Chip/ChipSubscription.php:367 — `toArray()/toJson()` serialize `recurring_token` (a reusable payment credential). These arrays flow into logs, queue payloads, Telescope, exception context. Fix: drop recurring_token from toArray/toJson; expose only via explicit recurringToken() accessor. Confidence: high.

4. [high/bug] src/Actions/CreatePayment.php:45-53 — Rate-limit exception swallowed and error leakage. `catch (Throwable)` only rethrows PaymentFailedException, so PaymentOperationRateLimitedException (extends CashierException) from Stripe charge's limiter is wrapped into PaymentFailedException, losing retryAfter semantics; raw `$e->getMessage()` is copied into message+details, leaking internal (DB/API) messages. Fix: rethrow PaymentOperationRateLimitedException explicitly; log the original and use a generic public message. Confidence: high.

5. [high/bug] src/Gateways/StripeGateway.php:140-157 — Full refunds send `'amount' => null`. `refund($id, $amount=null)` always posts amount key; Stripe expects the key omitted for full refunds, so the DEFAULT full-refund path is the broken one. Fix: build params then `unset($params['amount'])` when null; add negative-amount guard. Confidence: med.

6. [high/security] src/Gateways/StripeGateway.php:178,198 + src/Gateways/ChipGateway.php:166 — Owner check missing on checkout/subscription retrieval (IDOR). retrievePayment/retrieveInvoice verify the Stripe customer / CHIP client maps to an in-scope billable, but retrieveCheckout/retrieveSubscription (both gateways) fetch by raw ID with no ownership check. Fix: apply the same belongs-to-current-owner/billable check to all four retrieve methods. Confidence: high.

7. [high/bug] src/Console/Commands/WebhookReplayCommand.php:29-72 — Replay-all is dead code that reports success; event-id path fabricates invalid payloads. `pendingWebhooks()` exists on no gateway (verified by grep), so the loop always processes 0 yet logs "completed". If it existed, OwnerBatchRunner::run invokes the closure once PER OWNER batch (verified) while the closure ignores the batch and replays everything → N× duplicate replays. Also hard-codes customers-package Customer model (fatal if absent) and the event-id path feeds `['event_id'=>...]` into the Stripe WebhookController without signature. Fix: implement pending-webhook listing per gateway or remove replay-all; pass owner into the closure; validate --gateway against supportedGateways(); resolve real stored payloads for event-id replay. Confidence: high.

MEDIUM
8. [medium/bug] src/Gateways/ChipGateway.php:124,136 + src/Gateways/Chip/ChipSubscriptionBuilder.php:235 — No rate limiting on CHIP charge/refund/subscription-create, while Stripe equivalents use PaymentOperationLimiter. Fix: wrap all three in PaymentOperationLimiter::run. Confidence: high.
9. [medium/performance] src/Checkout/CartCheckoutBuilder.php:166-182 — External gateway HTTP call runs INSIDE DB::transaction (process() → createCheckoutSession → Stripe/CHIP API), holding DB locks across network I/O. Fix: validate/allocate inventory in transaction, call gateway outside, compensate (release allocation) on failure. Confidence: high.
10. [medium/bug] src/Actions/CreatePayment.php:24, CreateSubscription.php:22, RefundPayment.php:19, CancelSubscription.php:19 — No input validation or owner authorization on any Action. Negative/zero amounts, empty paymentMethod/prices, empty subscription type, and billables/subscriptions owned by other owners are all accepted (full bodies read; zero guards). Fix: validate amount>0, non-empty method/prices/type, gateway in supportedGateways(), and assert billable/subscription owner ∈ OwnerContext before mutating. Confidence: high.
11. [medium/bug] src/Actions/RefundPayment.php:19-29 — `$options` accepted but never forwarded to `$gateway->refund($paymentId,$amount)`. Fix: forward or remove the parameter. Confidence: high.
12. [medium/performance] src/Gateways/Stripe/StripePayment.php:110-130,174-193 — isRefunded()/receiptUrl() build `new StripeClient($secret)` and do a synchronous charges->retrieve per call, bypassing Cashier::stripe(). In any list/table this is N+1 Stripe API calls. Fix: memoize/resolve via Cashier::stripe(), batch-expand latest_charge, never call per-row. Confidence: high.
13. [medium/performance] src/Concerns/ManagesGateway.php:169-278 + StripeGateway.php:279 + ChipGateway.php:300 + Support/UnifiedSubscription.php:158 — Unbounded fan-out + N+1: allGateway* loop every gateway sequentially; subscriptions() does unbounded ->get(); getStripeAmount() queries items per subscription; items() lazy-loads per subscription. Fix: paginate/limit, eager-load items, cache gateway fan-out per request. Confidence: high.
14. [medium/bug] src/Support/UnifiedInvoice.php:46,61 — fromStripe() calls `$invoice->invoicePdf()`, which exists on NEITHER vendor Invoice NOR the wrapper (verified) → fatal Error if invoked (currently dead code; only fromGateway is called). fromChip() hardcodes currency 'MYR'. Fix: remove/repair dead constructors; use contract currency. Confidence: high.
15. [medium/bug] src/Concerns/ManagesGateway.php:45-51 — setPreferredGateway() persists any string without supportsGateway() check and assumes a preferred_gateway column (SQL error if absent); later gateway() throws on the bad value. Fix: validate + fail fast. Confidence: high.
16. [medium/security] src/Events/PaymentEvent.php:14 + WebhookReceived.php:14 — SerializesModels events carry non-serializable payloads: PaymentContract implementations (wrap Stripe SDK objects) and ?Request. Queued listeners will fail to serialize or bloat queues; full webhook payloads (PAN-adjacent data) sit in queued payloads. Fix: store scalar IDs/amount snapshots, re-resolve in the listener; never queue Request. Confidence: med.

LOW
17. [low/bug] src/Cashier.php:160-170 — formatAmount() computes $locale then ignores it (`Money->format()` without locale). Fix: thread locale into formatter or drop the param. Confidence: high.
18. [low/bug] src/Concerns/Billable.php:128-131 — onGenericTrial() calls ->isFuture() on trial_ends_at, fatal if the model lacks a datetime cast. Fix: CarbonImmutable::parse guard. Confidence: med.
19. [low/bug] src/Concerns/Billable.php:57-90 — allSubscriptions()/findSubscription()/onTrialOnAny() swallow ALL Throwable, masking API outages/misconfig as "not subscribed". Fix: catch gateway-specific exceptions only; log the rest. Confidence: high.
20. [low/security] src/Gateways/ChipGateway.php:506-516 — customerPortalUrl() interpolates user-supplied $options['panel'] into a route name (bounded by Route::has, but leaks internal panel URLs; falls back to caller $returnUrl = open-redirect if caller passes user input). Fix: allowlist panel IDs; validate returnUrl host. Confidence: med.
21. [low/performance] src/Support/OwnerScopedQuery.php:14,73-80 — static Schema::hasColumn cache never invalidated (Octane-stale across migrations); first touch per table hits information_schema. Fix: TTL/reset hook on Octane RequestReceived. Confidence: med.
22. [low/bug] Owner-mode detection diverges: AbstractGateway uses model::ownerScopeConfig() while ChipGateway delegates to CashierChip::findBillable gated by config cashier-chip.features.owner.enabled (verified owner-aware, fail-closed) → scoping disagrees when the two flags differ. Fix: single owner-mode source of truth. Confidence: med.
23. [low/process] No tests/, routes/, or migrations/ in-package (verified) — billing-critical money/webhook paths have zero package coverage; repo standard is Pest + PHPStan 6. Fix: add Pest coverage for Actions, builders, webhook handling. Confidence: high.

POSITIVES (brief)
- Money consistently int minor units + MoneyFormatter; per-gateway currency/locale config.
- Fail-closed owner patterns: OwnerScopedQuery::empty (static `1 = 0`, no user input in raw SQL), AbstractGateway::resolveBillableByGatewayId returns null without owner.
- Octane-safe design: Cashier statics snapshot/restore + forgetDrivers listener (Manager::forgetDrivers verified to exist); CartIntegrationRegistrar re-wrap guard.
- #[SensitiveParameter] on payment methods; Stripe webhook verification via SDK constructEvent (when called); PaymentOperationLimiter on Stripe charge/refund/subscription-create; secrets via env config only.
- No eval/unserialize/file-op/XSS/mass-assignment surfaces in package scope.

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### cashier (no models by design)
Bugs:
- `StripeGateway:413-423` HIGH — re-encode `json_encode($payload)` breaks Stripe HMAC (needs raw body).
- `SyncWebhook:21-35` HIGH — never calls `verifyWebhookSignature()` (same for `WebhookReplayCommand:33,65`).
- `StripeGateway:147-150` MEDIUM — explicit `'amount'=>null` risks full-refund rejection (omit key).
- `retrievePayment/Invoice:218-261` MEDIUM — cross-owner returns null, indistinguishable from 404.
- `OwnerScopedQuery:17,73-80` LOW — static `hasColumn` cache never cleared; stale after migration under Octane.
Security: Stripe primitive correct but unenforced HIGH — `Webhook::constructEvent` + empty-secret→false GOOD, but callers never verify.
Performance:
- Remote N+1 HIGH — `invoices():297-315` per-invoice `asStripeInvoice()` API fetch.
- `subscriptions():279-289` no pagination LOW.

### Prior-audit fix-first rows
| 6 | cashier | `Gateways/StripeGateway.php:413-423` | `handleWebhook` re-encodes body → Stripe HMAC never verifies | HIGH |
| 7 | cashier | `Actions/SyncWebhook.php:21-35` | No enforced `verifyWebhookSignature()` (same for `WebhookReplayCommand`) | HIGH |
| — | cashier | `Webhook::constructEvent` unenforced | Primitive correct, callers never verify → forged-event processing | HIGH |

### Verification verdicts

- [R1:#1] DOWNGRADED (was critical) high security | cashier/src/Actions/SyncWebhook.php:22 | "Webhooks handled without": never verifies; Stripe re-encode breaks HMAC; CHIP ignores headers. DUP AUD:B1,B2,B6,Q#1-3.
- [R1:#2] CONFIRMED critical bug | cashier/src/Checkout/CartCheckoutBuilder.php:285 | "Cart→CHIP checkout creates": price() posts amount 0; PurchasesApi rejects only <0, RM0.00 live.
- [R1:#3] CONFIRMED high security | cashier/src/Gateways/Chip/ChipPayment.php:439 | "toArray()/toJson() serialize": recurring_token serialized into logs/queues/Telescope.
- [R1:#4] CONFIRMED high bug | cashier/src/Actions/CreatePayment.php:45 | "Rate-limit exception swallowed": limiter exc wrapped, retryAfter lost; raw message leaked.
- [R1:#5] DOWNGRADED (was high) medium bug | cashier/src/Gateways/StripeGateway.php:140 | "Full refunds send": 'amount'=>null always posted; DUP AUD:B3 adopted MEDIUM.
- [R1:#6] CONFIRMED high security | cashier/src/Gateways/StripeGateway.php:178 | "Owner check missing": checkout/subscription retrieval unchecked (CHIP retrieveSubscription is scoped).
- [R1:#7] CONFIRMED high bug | cashier/src/Console/Commands/WebhookReplayCommand.php:29 | "Replay-all is dead": 0-op success; closure ignores owner batch; hard-coded Customer; forged event-id.
- [R1:#8] CONFIRMED medium bug | cashier/src/Gateways/ChipGateway.php:124 | "No rate limiting": CHIP charge/refund/sub-create lack PaymentOperationLimiter.
- [R1:#9] CONFIRMED medium perf | cashier/src/Checkout/CartCheckoutBuilder.php:166 | "External gateway HTTP": gateway API call inside DB::transaction holds locks over I/O.
- [R1:#10] CONFIRMED medium bug | cashier/src/Actions/CreatePayment.php:24 | "No input validation": all 4 Actions lack validation + owner authorization.
- [R1:#11] CONFIRMED medium bug | cashier/src/Actions/RefundPayment.php:19 | "$options accepted but": $options never forwarded to gateway refund().
- [R1:#12] CONFIRMED medium perf | cashier/src/Gateways/Stripe/StripePayment.php:110 | "isRefunded()/receiptUrl() build": new StripeClient + charges.retrieve per call; N+1 API.
- [R1:#13] CONFIRMED medium perf | cashier/src/Concerns/ManagesGateway.php:169 | "Unbounded fan-out +": allGateway* loop; subscriptions unbounded get; per-sub queries.
- [R1:#14] CONFIRMED medium bug | cashier/src/Support/UnifiedInvoice.php:46 | "fromStripe() calls $invoice->invoicePdf()": invoicePdf() absent, fatal if called; fromChip MYR hardcoded.
- [R1:#15] CONFIRMED medium bug | cashier/src/Concerns/ManagesGateway.php:45 | "setPreferredGateway() persists any": unvalidated string persisted; assumes column exists.
- [R1:#16] CONFIRMED medium security | cashier/src/Events/PaymentEvent.php:14 | "SerializesModels events carry": PaymentContract + ?Request queued; bloat/fail risk.
- [R1:#17] CONFIRMED low bug | cashier/src/Cashier.php:160 | "formatAmount() computes $locale": $locale computed then ignored by Money->format().
- [R1:#18] CONFIRMED low bug | cashier/src/Concerns/Billable.php:128 | "onGenericTrial() calls ->isFuture()": fatal if model lacks datetime cast.
- [R1:#19] CONFIRMED low bug | cashier/src/Concerns/Billable.php:57 | "swallow ALL Throwable": outages/misconfig masked as not-subscribed.
- [R1:#20] CONFIRMED low security | cashier/src/Gateways/ChipGateway.php:506 | "customerPortalUrl() interpolates user-supplied": panel into route name; returnUrl fallback open-redirect.
- [R1:#21] CONFIRMED low perf | cashier/src/Support/OwnerScopedQuery.php:14 | "static Schema::hasColumn cache": never invalidated (Octane-stale); DUP AUD:B5.
- [R1:#22] CONFIRMED low bug | cashier/src/Gateways/AbstractGateway.php:244 | "Owner-mode detection diverges": ownerScopeConfig vs CashierChip::findBillable flags disagree.
- [R1:#23] CONFIRMED low process | cashier/:0 | "No tests/, routes/,": no Test.php/routes/migrations in package; money paths uncovered.
- [AUD:B1] ADOPTED high bug | cashier/src/Gateways/StripeGateway.php:413 | DUP R1:#1; re-encode breaks HMAC. Counted once.
- [AUD:B2] ADOPTED high bug | cashier/src/Actions/SyncWebhook.php:21 | DUP R1:#1; verify never enforced. Counted once.
- [AUD:B3] ADOPTED medium bug | cashier/src/Gateways/StripeGateway.php:147 | DUP R1:#5; explicit null amount. Counted once.
- [AUD:B4] ADOPTED medium bug | cashier/src/Gateways/StripeGateway.php:218 | cross-owner null indistinguishable from 404.
- [AUD:B5] ADOPTED low perf | cashier/src/Support/OwnerScopedQuery.php:17 | DUP R1:#21; static cache. Counted once.
- [AUD:B6] ADOPTED high security | cashier/src/Gateways/StripeGateway.php:385 | DUP R1:#1; primitive correct, callers never verify. Counted once.
- [AUD:B7] ADOPTED high perf | cashier/src/Gateways/StripeGateway.php:297 | invoices(): per-invoice asStripeInvoice API fetch.
- [AUD:B8] ADOPTED low perf | cashier/src/Gateways/StripeGateway.php:279 | subscriptions(): unbounded get; partial overlap R1:#13.
- [AUD:Q#1] ADOPTED high bug | cashier/src/Gateways/StripeGateway.php:413 | DUP R1:#1; queue row 6. Counted once.
- [AUD:Q#2] ADOPTED high bug | cashier/src/Actions/SyncWebhook.php:21 | DUP R1:#1; queue row 7. Counted once.
- [AUD:Q#3] ADOPTED high security | cashier/src/Gateways/StripeGateway.php:385 | DUP R1:#1; constructEvent row. Counted once.

---

## cashier-chip

### E2E findings (verbatim)

E2E review: packages/cashier-chip (Laravel 8.4-style monorepo pkg, CHIP recurring billing). Read: CONTEXT.md, config, all src (Subscription, Billing, Actions, Concerns, Payment, Invoice, Console, Listeners, Support, Testing fakes), 4 migrations, 2 factories, invoice.blade.php; cross-checked packages/chip PurchaseBuilder. No routes/ or tests/ dirs exist.

FINDINGS (severity | category | file:line | title — description | evidence | recommendation | confidence):

1. CRITICAL | bug | src/Concerns/ManagesInvoices.php:136 — invoice() calls undefined PurchaseBuilder::addProduct(), fatal at runtime. `$builder->addProduct($tab['name'], $tab['price'], $tab['quantity']);` but PurchaseBuilder only defines addProductMoney/addProductObject/addProductCents (+addLineItem), no __call (verified in packages/chip/src/Builders/PurchaseBuilder.php:69,128,170). So tab()/invoice()/invoicePrice()/invoiceFor() always throw Error. Fix: use addProductCents(...) (minor units, consistent with repo money rule). Confidence: high.

2. CRITICAL | bug | src/Subscription/Subscription.php:702-709 — swap() drops unit_amount, renewals become free. swap() rebuilds items via createTrustedSubscriptionItem with only owner/chip_id/chip_product/chip_price/quantity — no unit_amount — while calculateSubscriptionAmount() (:1293) = sum(unit_amount*qty). After any swap, renewal amount = 0 (and 0 fails isAmountWithinBounds → renewals fail, or worse, zero-charge if bounds change). Evidence: attrs array lacks 'unit_amount' key vs addPrice() (:1065) which passes it. Fix: carry over existing item unit_amount (match by price) and accept per-price unit_amount option. Confidence: high.

3. HIGH | bug | src/Actions/SyncChipPurchaseStatus.php:98-112 — webhook handler extends next_billing_at on every purchase.paid with no dedup; duplicate/redelivered webhooks double-extend billing, and it unconditionally forceFills chip_status='active' (resurrects canceled/past-due subs, ignores RenewalAttempt state). Evidence: `syncSubscriptionPayment()` does `CarbonImmutable::now()->add($interval,$count)` with no purchase_id idempotency check; string statuses bypass enum. Fix: record processed purchase ids (or complete matching RenewalAttempt by purchase_id/metadata) and skip repeats; don't overwrite Canceled without explicit resume. Confidence: high.

4. HIGH | bug | src/Console/RenewSubscriptionsCommand.php:136-171 — renewal charge has no idempotency key; transport-unknown retry can double-charge. ChargeChipCustomer::run is called without 'idempotency_key' (only reference "Renewal {attempt}"), and any Throwable → recordUnknown(attempt, null, 'TRANSPORT_OUTCOME_UNKNOWN'); next run claims a NEW attempt (existing-claim check only matches status='claimed') and charges again for the same period even if the first charge landed. Fix: pass idempotency_key derived from attempt id (IdempotencyKey::apply exists for this) and reconcile unknown attempts via getPurchase before recharging. Confidence: high.

5. HIGH | bug | src/Actions/CreateChipSubscription.php:44-53 — coupon accepted without validity check. Unlike checkout paths (SubscriptionBuilder.php:357 validateCouponForCheckout) and Subscription::applyCoupon, creation only does retrieveCoupon()+calculateDiscount with no isValid()/expiry/status check, then records usage. Expired/inactive voucher codes get discounts. Fix: call validateCouponForSubscriptionApplication-equivalent before applying. Confidence: high.

6. MEDIUM | security | src/Payment/StoredPaymentMethod.php (~model, migration 2000_03_01_000003) + src/Subscription/Subscription.php:recurring_token — recurring tokens stored plaintext at rest. No 'encrypted' cast on StoredPaymentMethod.recurring_token or Subscription.recurring_token; DB leak = reusable payment credentials. Fix: encrypted casts + data migration. Confidence: high (fact); med (exploit depends on DB access).

7. MEDIUM | bug | src/Subscription/Subscription.php:1293 vs :1146-1150 — renewals ignore coupon_discount (overcharge). calculateSubscriptionAmount() sums items only; ClaimRenewalAttempt and charge() use it, while upcomingInvoice() subtracts coupon_discount. Discounted subscriptions renew at full price; coupon duration never honored at renewal. Fix: centralize amount calc (items − applicable discount honoring duration) used by both. Confidence: high.

8. MEDIUM | security | src/Concerns/PerformsCharges.php:213-221 — findPayment() has no ownership check (IDOR/info disclosure). It returns `new Payment(getPurchase($id))` for any id, unlike findInvoice() (ManagesInvoices.php:59-84) which verifies purchase client id === billable chip id. Any billable can pull arbitrary purchase details. Fix: mirror findInvoice's client-id check. Confidence: high.

9. MEDIUM | security | src/Invoice/Invoice.php:366-389 — header injection via download filename. `download()` builds `Content-Disposition: attachment; filename="invoice-{$this->number()}.pdf"` where number() = CHIP purchase reference (attacker-influenced); downloadAs() takes raw $filename. Embedded quote/CRLF breaks the header. Fix: sanitize (strip control chars/quotes, fallback to id) or use RFC 5987 filename*. Confidence: med.

10. MEDIUM | bug | src/Billing/Checkout.php:74-100 — Checkout::create lacks amount-bounds/currency validation. Charge paths call Cashier::assertAmountWithinBounds + uppercase currency; Checkout::create passes $amount straight to addProductCents($name,$amount) (negative/zero/huge allowed) and raw $options['currency']. Fix: assert bounds + normalize/whitelist currency. Confidence: high.

11. MEDIUM | bug | src/Concerns/ManagesPaymentMethods.php:~200-230 — createSetupPurchase lets raw options['chip'] override client_id/brand_id/purchase. `$purchaseData = array_merge([defaults incl. client_id, purchase...], $options['chip'] ?? [])` so a caller can redirect setup to another client/brand or swap products. Fix: allowlist chip overrides (redirects/callback only), never client_id/brand_id/purchase. Confidence: med-high.

12. MEDIUM | bug | src/Subscription/Subscription.php:858-875 — charge() null-derefs when billable missing. `$this->customer->defaultPaymentMethod()` / `$this->customer->chargeWithRecurringToken(...)` with no null check on the morphTo. Fix: throw InvalidCustomer/LogicException when billable absent. Confidence: high (code), med (trigger requires orphaned sub).

13. MEDIUM | bug | multiple — billing_interval is an unvalidated free string fed to Carbon ->add($interval,$count): SubscriptionBuilder.php:calculateNextBillingDate, SyncChipPurchaseStatus.php:108, RenewSubscriptionsCommand.php:recordSuccess, Subscription.php:currentPeriodStart. Corrupt/invalid value crashes renewals (stuck billing) since billingInterval() accepts anything. Fix: whitelist day/week/month/year at builder + migration/model guard. Confidence: med-high.

14. MEDIUM | performance | src/Subscription/Subscription.php:1188-1215 + src/Concerns/ManagesInvoices.php:28-46 — remote N+1: invoices() issues one live Cashier::chip()->getPurchase() per renewal attempt, unbounded; billable invoices() fans out over all subscriptions. Slow + API rate-limit risk. Fix: paginate/limit attempts, cache purchase payloads, lazy-load per invoice. Confidence: high.

15. MEDIUM | performance | src/Concerns/ManagesSubscriptions.php:~168-174 — subscription($type) loads entire subscription history (+items+billable) to find one type; unbounded growth per billable. Fix: constrained query first, hydrate relation only on hit. Confidence: med.

16. LOW | bug | src/Concerns/ManagesInvoices.php:44 — sortByDesc('created_at') on Invoice value objects is a no-op (Invoice has no created_at property). Fix: sort by date(). Confidence: high.

17. LOW | bug | database/migrations/2000_03_01_000001 + 000002 — subscription_items table created twice (000001 creates both tables; 000002 repeats). Harmless due to hasTable guard but confusing/schema-drift risk. Fix: remove duplication. Confidence: high.

18. LOW | security | multiple — redirect/webhook URLs unvalidated (success_url/failure_url/cancel_url/webhook_url in Checkout/PerformsCharges/ChargeChipCustomer; success_callback in createSetupPurchase). Open-redirect/SSRF sink if user input ever flows in. Fix: validate http(s) + allowlist host where user-influenced. Confidence: low-med.

19. LOW | security/perf | src/Actions/SyncChipPurchaseStatus.php:saveRecurringToken + HandlePurchasePreauthorized — entire CHIP purchase payload persisted into payment-method metadata (PII retention, unbounded jsonb growth per save). Fix: store only needed fields (masked_pan last4, method, purchase id). Confidence: high (fact).

20. LOW | bug/perf | src/Payment/PaymentMethodStore.php:86-95 — default flip (update all false, then save) is non-atomic, no transaction/partial-unique index; races can yield 0–2 defaults. Also defaultForBillable silently falls back to newest, so hasDefaultPaymentMethod() is true even when nothing is marked default. Fix: transaction + unique partial index. Confidence: med.

21. LOW | bug | RenewalAttempt — not owner-scoped (no owner cols, no HasOwner) while siblings are; no unique (subscription_id, period_key); expired 'claimed' leases accumulate as dead rows (never reclaimed/cleaned). Missing index on period_key/lease_expires_at. Fix: add indexes + unique constraint + lease-reaper. Confidence: med.

22. LOW | perf | src/Payment/PaymentMethod.php:isDefault + ManagesPaymentMethods.php:deletePaymentMethods — isDefault() re-queries defaultPaymentMethod per call (N+1 in listings/toArray); deletePaymentMethods does per-token CHIP API delete then deleteAllForBillable (redundant). Fix: compare stored flags; single bulk path. Confidence: med.

23. LOW | bug | src/Subscription/Subscription.php:108-131 — recurring_token in $fillable (sensitive mass-assignable). Creation uses forceFill, so fillable entry only widens request-mass-assignment risk. Fix: remove from fillable. Confidence: low (internal-use assumption).

24. LOW | process | package has no tests/ directory (repo stack is Pest/PHPStan-6); critical paths (renewals, webhooks, swap) unverified. Parked env note: none.

POSITIVES (brief): owner fail-closed behavior in listeners (HandlePurchasePaid/Failure/Preauthorized) + Cashier::findBillableForWebhook; PaymentMethodStore cross-tenant write guards with withoutOwnerScope detection; rate limiting on charge paths; amount bounds on charge/createPayment/checkoutCharge; SensitiveParameter on token params; renewal claim uses lockForUpdate + lease + frozen amount_minor; uuid PKs; no DB FK constraints/cascades (foreignUuid w/o constrained) per repo rules; money in minor units (addProductCents) except finding #1's dead call; Octane static-state restore in Cashier; invoice.blade.php uses escaped {{ }} throughout; findInvoice ownership check is the right pattern (findPayment should copy it).

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### cashier-chip
Bugs:
- DONE (2026-09-13, §8 item 4) — No `(subscription_id,period_key)` unique MEDIUM-HIGH — `ClaimRenewalAttempt:21-69` SELECT-then-INSERT serialized only by subscription lock; crashed lease → double-bill. Fixed: kept additive (partial unique where not null) with atomic claim + 23000 rescue.
- `WebhookCommand:55` MEDIUM — dead key `cashier-chip.webhooks.verify_signature` display-only; real switch in `chip`.
Security:
- `PaymentMethodStore:72-182` GOOD — scoped → `withoutOwnerScope` re-check → `AuthorizationException` on cross-tenant.
- `validate_billable_owner=false` kill-switch LOW.
- DONE (2026-09-13, §8 item 12) — `RenewalAttempt` no owner columns MEDIUM — isolation via `belongsTo subscription` join only. Fixed: kept additive (owner columns + chunked backfill from parent subscriptions); `HasOwner`/`HasOwnerScopeConfig` with inheritance and `OwnerWriteGuard` on claim paths.
Performance: `ChipSubscription items` N+1 MEDIUM (corrected citation `:1158` with `loadMissing:1140`) — eager-load `items` at query site.

### Migration-batch rows (§8, code may already be fixed)
| 4 | cashier-chip `(subscription_id, period_key)` unique (§5) | Kept additive (`2026_09_13_000001_*`); atomic claim + 23000 rescue | `packages/cashier-chip/docs/09-subscriptions.md` |
| 12 | `RenewalAttempt` owner columns (§5) | Kept additive (columns + chunked backfill); `HasOwner` + inheritance + guards | `packages/cashier-chip/docs/01-overview.md`, `02-installation.md`, `09-subscriptions.md`, `11-testing.md` |

### Verification verdicts

- [R1:#1] CONFIRMED critical bug | cashier-chip/src/Concerns/ManagesInvoices.php:136 | "invoice() calls undefined": addProduct absent (Money/Object/Cents/LineItem only), fatal.
- [R1:#2] CONFIRMED critical bug | cashier-chip/src/Subscription/Subscription.php:702 | "swap() drops unit_amount,": items rebuilt w/o amount; renewal sums to 0.
- [R1:#3] CONFIRMED high bug | cashier-chip/src/Actions/SyncChipPurchaseStatus.php:98 | "webhook handler extends": no purchase-id dedup; force-active resurrects canceled.
- [R1:#4] DOWNGRADED (was high) medium bug | cashier-chip/src/Console/RenewSubscriptionsCommand.php:136 | "renewal charge has": no idempotency_key; unique blocks re-claim but 'unknown' never reconciled.
- [R1:#5] CONFIRMED high bug | cashier-chip/src/Actions/CreateChipSubscription.php:45 | "coupon accepted without": no validity check; expired codes discounted + usage recorded.
- [R1:#6] CONFIRMED medium security | cashier-chip/src/Payment/StoredPaymentMethod.php:97 | "recurring tokens stored": no encrypted cast on either model; DB leak = reusable credentials.
- [R1:#7] CONFIRMED medium bug | cashier-chip/src/Subscription/Subscription.php:1293 | "renewals ignore coupon_discount": claim charges items sum; only upcomingInvoice subtracts.
- [R1:#8] CONFIRMED medium security | cashier-chip/src/Concerns/PerformsCharges.php:213 | "findPayment() has no": any-id purchase fetch; no client-id check (cf findInvoice).
- [R1:#9] CONFIRMED medium security | cashier-chip/src/Invoice/Invoice.php:366 | "header injection via": unsanitized number()/filename in Content-Disposition.
- [R1:#10] CONFIRMED medium bug | cashier-chip/src/Billing/Checkout.php:81 | "Checkout::create lacks amount-bounds/currency": no bounds assert; raw currency/amount.
- [R1:#11] CONFIRMED medium bug | cashier-chip/src/Concerns/ManagesPaymentMethods.php:201 | "createSetupPurchase lets raw": options['chip'] overrides client_id/brand_id/purchase.
- [R1:#12] CONFIRMED medium bug | cashier-chip/src/Subscription/Subscription.php:858 | "charge() null-derefs when": $this->customer unguarded (orphaned subs); cf ?-> in recurringToken().
- [R1:#13] CONFIRMED medium bug | cashier-chip/src/Subscription/SubscriptionBuilder.php:204 | "billing_interval is an": free string into Carbon add() at 4+ sites; corrupt value stalls billing.
- [R1:#14] CONFIRMED medium perf | cashier-chip/src/Subscription/Subscription.php:1188 | "remote N+1: invoices()": unbounded attempts x live getPurchase; billable fans out.
- [R1:#15] CONFIRMED medium perf | cashier-chip/src/Concerns/ManagesSubscriptions.php:169 | "subscription($type) loads entire": full history + items + billable to find one type.
- [R1:#16] CONFIRMED low bug | cashier-chip/src/Concerns/ManagesInvoices.php:43 | "sortByDesc('created_at') on": Invoice has date(), no created_at, no-op sort.
- [R1:#17] FALSE low bug | cashier-chip/database/migrations/2000_03_01_000001:22 | "subscription_items table created": 000001 creates subscriptions only; no duplication in current source.
- [R1:#18] CONFIRMED low security | cashier-chip/src/Concerns/ManagesPaymentMethods.php:218 | "redirect/webhook URLs unvalidated": redirect/callback URLs unvalidated; user input = open-redirect.
- [R1:#19] CONFIRMED low security | cashier-chip/src/Actions/SyncChipPurchaseStatus.php:60 | "entire CHIP purchase": full payload persisted to method metadata (PII retention/growth).
- [R1:#20] CONFIRMED low bug | cashier-chip/src/Payment/PaymentMethodStore.php:86 | "default flip (update": update-all-false then save, no txn/index; races yield 0-2 defaults.
- [R1:#21] FIXED low bug | cashier-chip/database/migrations/2000_03_01_000004:17 | "RenewalAttempt — not": owner cols + HasOwner + unique present; residual: no lease reaper.
- [R1:#22] CONFIRMED low perf | cashier-chip/src/Payment/PaymentMethod.php:168 | "isDefault() re-queries defaultPaymentMethod": per-call query; per-token delete + deleteAll redundant.
- [R1:#23] CONFIRMED low bug | cashier-chip/src/Subscription/Subscription.php:108 | "recurring_token in $fillable": sensitive mass-assignable; creation uses forceFill anyway.
- [R1:#24] CONFIRMED low process | cashier-chip/:0 | "package has no": no Test.php in package; renewals/webhooks/swap unverified.
- [AUD:B1] FIXED med-high bug | cashier-chip/database/migrations/2000_03_01_000004:30 | unique present + atomic claim w/ 23000 rescue; s8 item 4 done.
- [AUD:B2] ADOPTED medium bug | cashier-chip/src/Console/WebhookCommand.php:55 | verify_signature key display-only; real switch in chip.
- [AUD:B3] ADOPTED low security | cashier-chip/src/Payment/PaymentMethodStore.php:72 | positive: cross-tenant write guards verified; no action.
- [AUD:B4] ADOPTED low security | cashier-chip/config/cashier-chip.php:44 | validate_billable_owner kill-switch exists, default true.
- [AUD:B5] FIXED medium security | cashier-chip/src/Subscription/RenewalAttempt.php:36 | owner cols + HasOwner/inheritance + guards; s8 item 12 done.
- [AUD:B6] ADOPTED medium perf | cashier-chip/src/Subscription/Subscription.php:1158 | items map; eager-load at query site (FALSE-list rejects wrong-line dup).
- [AUD:Q#1] ADOPTED med-high bug | cashier-chip/database/migrations/2000_03_01_000004:30 | DUP AUD:B1; migration row 4. Counted once.
- [AUD:Q#2] ADOPTED medium security | cashier-chip/src/Subscription/RenewalAttempt.php:36 | DUP AUD:B5; migration row 12. Counted once.

---

## checkout

### E2E findings (verbatim)

End-to-end review of packages/checkout (orchestrator: session → steps → pay → order). Deep-read CONTEXT.md, config, routes, both migrations, model, controllers, all 6 actions, CheckoutService, StepExecutor, registries, all 11 steps, 3 payment processors, webhook validator/processor, listeners, job, views (partial), plus Spatie transition + OwnerContextJob trait to verify assumptions.

POSITIVES (brief): callback token is random 40-char, hash_equals-compared, TTL + single-use consumed_at (PaymentCallbackController.php:139-168); gateway-bound callback routes + gateway-match check; webhook signature verification fails closed in production (CheckoutSpatieSignatureValidator.php:27-42); namespaced chk_<uuid> refs with strict UUID parse; atomic payment-attempt increment with retry cap (ProcessPaymentStep.php:368-389); lockForUpdate callback handling; strict int-amount + currency reconciliation before confirmPayment (CreateOrderStep.php:338-357); owner re-entry in webhooks/jobs/steps; discount capped at subtotal; vouchers redeemed only after order exists; no FK constraints, uuid PK, int minor-unit money, no SoftDeletes — all per repo rules.

FINDINGS:

1. HIGH / bug — packages/checkout/src/Integrations/Payment/CashierProcessor.php:71 — Stripe webhook stores event id as payment_id. `$paymentId = $payload['id'] ?? ...` captures the top-level Stripe event id (`evt_*`), not the payment object id (`data.object.id`). This is persisted to `payment_id`/`transaction_id` and later used for refunds, voids and status checks, which will target a non-payment id. Evidence: `extractSessionId` correctly reads `data.object.*` (ProcessCheckoutPaymentNotification.php:115-119) but `handleCallback` does not. Fix: prefer `data_get($payload,'data.object.id')`, fall back to top-level `id` only for non-Stripe shapes. Confidence: high.

2. HIGH / security — packages/checkout/src/Services/CheckoutService.php:51-71 — `startCheckout($cartId, $customerId)` persists an arbitrary caller-supplied `customer_id` with no auth/ownership check, and resolves the cart via `CartManager::getById($cartId)` with no visible owner scoping. Downstream `ResolveCustomerStep`/`PersistCustomerStep` then load that customer's saved addresses/PII into the session. A caller passing a victim's customer_id gets an IDOR/PII leak unless the host app pre-validates (nothing in this package's contract requires it). Fix: verify customer belongs to auth user/owner, or document host MUST validate. Confidence: med (host may validate; package itself does not).

3. MEDIUM / security — packages/checkout/src/Models/CheckoutSession.php:80-116 — `$fillable` includes `owner_type/owner_id`, `status`, `order_id`, `payment_id`, all totals (`subtotal…grand_total`), and `payment_data`. Any host-side mass assignment (`update($request->all())`) enables tenant-hopping, status tampering, and free orders via `grand_total=0` (ProcessPaymentStep.php:77 treats `grand_total<=0` as `free_order`). Fix: remove server-computed fields from `$fillable` (use `$guarded` or explicit `forceFill` in services). Confidence: high (hardening; exploit needs host mass-assignment).

4. MEDIUM / security — packages/checkout/src/Steps/PersistCustomerStep.php:104-125 — `resolveStoredActor` resolves an arbitrary morph class from session `payment_data.checkout_actor.type` (`Relation::getMorphedModel($actorType) ?? $actorType`, only `class_exists` + `is_subclass_of(Model)` gate) and queries it unscoped. `payment_data` is fillable JSON; if a host lets user input reach it, an attacker can bind an arbitrary user model as checkout actor (customer merge/account confusion) and trigger unscoped cross-tenant reads. Fix: allowlist actor morphs (e.g. User/Customer) + owner-consistency check. Confidence: med.

5. MEDIUM / bug — packages/checkout/src/Steps/ProcessPaymentStep.php:350-365 — `buildCallbackUrl` hardcodes the `session` query param, ignoring `checkout.defaults.session_query_param`, while the controller only reads the configured param or `checkout_session_id` (PaymentCallbackController.php:110-111). Any host that customizes the param breaks ALL payment callbacks. Fix: use the config value when building URLs. Confidence: high (default config unaffected).

6. MEDIUM / bug — packages/checkout/src/Integrations/Payment/ChipProcessor.php:78-87 (same in CashierChipProcessor.php:78-87) — `handleCallback` passes raw `$payload['purchase']['total']` / `['currency']` (mixed float/string) straight into `PaymentResult(?int $amount)`. Unlike `CashierProcessor` (which validates via `minorAmount`/`currency`), un-normalized values can fail Spatie Data coercion or later be rejected by `CreateOrderStep::minorAmount()`, failing the strict amount reconciliation for genuinely-paid CHIP orders (stuck in retryable state). Fix: normalize with the same `minorAmount`/`currency` helpers. Confidence: med (depends on live CHIP payload types; the missing normalization vs the sibling processor is factual).

7. MEDIUM / bug — packages/checkout/src/Support/CheckoutCallbackStatePolicy.php:35-38 + src/Services/CheckoutService.php:334-347 — callback idempotency only covers `Completed`. Repeated failure/cancel webhooks re-run compensate (no-op) but `handleCheckoutFailureResult` re-updates `error_message` and re-dispatches `CheckoutFailed` every time (same for `CheckoutCancelled` on the cancel path), causing duplicate customer notifications/side effects on gateway retries. Fix: return early when already in `PaymentFailed`/`Cancelled` for matching callback types. Confidence: high.

8. MEDIUM / bug+performance — packages/checkout/src/Steps/CalculatePricingStep.php:170-230 — `resolvePriceable` does one unscoped `$class::query()->find()` per cart item (N+1) from snapshot-supplied `associated_model.class/id`; the owner-tuple cross-check only runs when `checkout.owner.enabled` (default false). With owner mode off, crafted snapshot entries can resolve arbitrary `Priceable` models (price oracle / wrong-price risk, bounded by cart package's own validation of `associated_model`). Fix: batch-load per class, always scope/validate resolved models. Confidence: high (N+1); med (tenant aspect).

9. LOW / bug — packages/checkout/src/Support/ChipPurchasePayloadBuilder.php:13-20 — idempotency key is the bare session id, reused across payment retries with new callback tokens; CHIP may return the original (possibly stale-amount) purchase on retry. Fail-closed via amount reconciliation, but retries can stick. Fix: include `payment_attempts` in the key. Also logs a warning on every call (log noise). Confidence: med.

10. LOW / bug — packages/checkout/src/Http/Controllers/PaymentCallbackController.php:188 — `callbackRouteMatchesGateway` validates the gateway only against `routes.callbacks.success` for all three callback types; asymmetric custom config (gateway missing from success map) wrongly rejects failure/cancel callbacks. Fix: check the map for the current type. Confidence: high.

11. LOW / security — packages/checkout/src/Http/Controllers/PaymentCallbackController.php:129-137 — `RateLimiter::hit` runs before token validation, so anyone knowing a session UUID can burn its 10/min budget and block legitimate callbacks (DoS); session id is also queried without UUID-shape validation. Mitigated by UUID unguessability. Fix: validate UUID format pre-query; count only failed token attempts (or key by IP+session). Confidence: high.

12. LOW / bug — packages/checkout/src/Models/CheckoutSession.php:278-325 + src/Actions/CheckoutFinalizer.php:29-33 — `transitionStatus()` calls Spatie `transitionTo()` (verified: `DefaultTransition::handle()` already `save()`s) and then performs a second direct `DB::table()->update()` despite the comment claiming an Eloquent bypass — redundant double write + duplicate `StateChanged`/model events per transition. `CheckoutFinalizer` uses raw `transitionTo` instead (inconsistent path, relies on the `updating` hook for `completed_at`). Fix: pick one persistence path. Confidence: high.

13. LOW / bug — packages/checkout/src/Services/CheckoutService.php:428-430 — failed payment verification returns `CheckoutResult::failed` with no state transition, `error_message`, or event (unlike every other failure path) — silent/observability gap. Likely intentional (stay retryable in `AwaitingPayment`); consider a distinct event or `verification_status` log. Confidence: high (behavior verified); low (whether undesired).

14. LOW / performance — pipeline issues ~2–3 writes per step (`setStepState` + `update`, StepExecutor.php:98-99,115 + each step's `update`/`calculateTotals`/`save`) ≈ 25–35 queries per checkout, plus per-step `recordCompensation` writes on failure. Fine at current scale; batch step-state updates if checkout throughput grows. Confidence: med.

15. LOW / security-note — PaymentCallbackController.php:243-286 `redirect($url)` uses config-controlled URLs with `{order_id}/{session_id}` substitution (server-side values, uuid-ish). Safe as long as redirect config stays server-controlled; do not ever source from request input. Informational. Confidence: high.

16. LOW / bug-smell — packages/checkout/src/Actions/EnsureCheckoutOfferProduct.php:112-117 — `basePriceForProduct` stores `max(price, compare)` as the selling price, which looks inverted (compare-at price should be the higher display value, not the charge price). Flag for product-team confirmation. Confidence: low.

NOT FOUND / verified absent: no `DB::table` apart from the PK-scoped transition write; no `whereRaw`/string-concatenated SQL; no `unserialize`/`eval`/shell exec; no SSRF (gateway redirect URLs built server-side via `route()`/`url()`); no path traversal or file APIs; inspected Blade views use escaped `{{ }}` output; no unbounded pagination endpoints; no cache usage (no stampede surface); no mutable static state (registry/resolver singletons hold config-driven registrations; Octane-safe); indexes exist on cart/customer/order/payment/status/expires_at (only exotic gap: no `[status, expires_at]` composite, and no expiry-sweeper job exists to use it).

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### checkout
Bugs:
- `CheckoutService:78-91` HIGH unscoped `resumeCheckout` (see queue).
- `CheckoutFinalizer:29-33` MEDIUM (was HIGH "completed_at=null") — bypasses `CheckoutSession::transitionStatus()`; `completed_at=null` disproved (`updating:382-383` still sets it); residual is inconsistent persistence path.
- `CheckoutSession:183,204,236` HIGH lost-update on JSON blobs; nested txn `:436` inside `:107` lengthens locks.
- `CheckoutService:51-71` MEDIUM — no owner assertion on `startCheckout`; cart/customer unvalidated.
- `CreateOrderStep:148-217` MEDIUM — index-aligned pricing (`$pricingItems[$index]`); join on `item_id`.
- `CreateOrderStep:326-329` MEDIUM — `'unknown'` gateway/txn fallbacks collapse `(order,gateway,transaction)` idempotency.
- `PaymentCallbackController:188` residual LOW — "failure/cancel never matches" FALSE (identical gateway keys under success/failure/cancel still pass `array_key_exists`); residual: type-segment not verified.
Security:
- `CheckoutSession:80-116` HIGH mass assignment (see queue).
- `startCheckout:51-71` MEDIUM — session can be ownerless/global.
- Rate-limit-before-verify DoS MEDIUM — `PaymentCallbackController:129-137` `hit()` before `hash_equals`; hit after verify or per-IP+session keys.
- `CheckoutSession:302-308` raw PK update LOW — intentional (commented bypass of Spatie loop); document only. Validator fails closed GOOD.
Performance:
- 20–30 `UPDATE checkout_sessions` per pipeline HIGH — per step `setStepState` + `update(current_step)` + domain updates ×~11 steps. Coalesce or defer to end.
- Per-line pricing loop MEDIUM — `CalculatePricingStep:112-159` per-line `calculate()`; `resolvePriceable:194` unscoped `find` per line then owner-compare (correct reject, still 1 query/line).
- Gateway calls inside txn MEDIUM — `processCheckout:107` txn wraps `stepExecutor->run`; move I/O outside.

### Prior-audit fix-first rows
| 8 | checkout | `Services/CheckoutService.php:78-91` | `resumeCheckout` uses unscoped `find()` — session enumeration | HIGH |
| 9 | checkout | `Models/CheckoutSession.php:80-116` | `owner_*/status/totals/order_id` fillable → free-order / hijack | HIGH |
| — | checkout | `CheckoutSession:183,204,236` | Lost-update on JSON blobs; nested txn lengthens locks | HIGH |

### Verification verdicts

- [R1:#1] CONFIRMED high bug | checkout/src/Integrations/Payment/CashierProcessor.php:71 | "Stripe webhook stores": top-level id (evt_*) stored as payment_id; siblings read data.object.
- [R1:#2] DOWNGRADED (was high) medium security | checkout/src/Services/CheckoutService.php:51 | "startCheckout($cartId, $customerId) persists": arbitrary customer/cart, no owner check; DUP AUD:B4,B9.
- [R1:#3] CONFIRMED high security | checkout/src/Models/CheckoutSession.php:80 | "$fillable includes owner_type/owner_id": status/totals/payment_data/owner fillable; free-order; DUP AUD:B8 HIGH.
- [R1:#4] CONFIRMED medium security | checkout/src/Steps/PersistCustomerStep.php:104 | "resolveStoredActor resolves an": morph class from fillable payment_data; unscoped query.
- [R1:#5] CONFIRMED medium bug | checkout/src/Steps/ProcessPaymentStep.php:350 | "buildCallbackUrl hardcodes the": 'session' hardcoded vs configurable query param.
- [R1:#6] CONFIRMED medium bug | checkout/src/Integrations/Payment/ChipProcessor.php:78 | "handleCallback passes raw": raw total/currency into ?int amount; no minorAmount normalization.
- [R1:#7] CONFIRMED medium bug | checkout/src/Support/CheckoutCallbackStatePolicy.php:35 | "callback idempotency only": Completed-only; repeat failure re-updates + re-dispatches CheckoutFailed.
- [R1:#8] CONFIRMED medium perf | checkout/src/Steps/CalculatePricingStep.php:170 | "resolvePriceable does one": per-item unscoped find; owner check only when enabled; DUP AUD:B13.
- [R1:#9] CONFIRMED low bug | checkout/src/Support/ChipPurchasePayloadBuilder.php:13 | "idempotency key is": bare session id reused across retries; warning logged per call.
- [R1:#10] CONFIRMED low bug | checkout/src/Http/Controllers/PaymentCallbackController.php:188 | "callbackRouteMatchesGateway validates the": success-map-only; FALSE-list: identical keys pass, residual type-segment.
- [R1:#11] CONFIRMED medium security | checkout/src/Http/Controllers/PaymentCallbackController.php:129 | "RateLimiter::hit runs": hit before token verify; no UUID check; DUP AUD:B10 adopted MEDIUM.
- [R1:#12] CONFIRMED low bug | checkout/src/Models/CheckoutSession.php:278 | "transitionStatus() calls Spatie": transitionTo then direct DB update; double write + events.
- [R1:#13] CONFIRMED low bug | checkout/src/Services/CheckoutService.php:428 | "failed payment verification": silent failed result; no transition/message/event (maybe intentional).
- [R1:#14] CONFIRMED high perf | checkout/src/Models/CheckoutSession.php:183 | "pipeline issues ~2–3": per-step update x11, ~25-35 queries; DUP AUD:B12 adopted HIGH.
- [R1:#15] CONFIRMED low security | checkout/src/Http/Controllers/PaymentCallbackController.php:247 | "redirect($url) uses config-controlled": config URLs + server ids; informational, keep server-sourced.
- [R1:#16] CONFIRMED low bug | checkout/src/Actions/EnsureCheckoutOfferProduct.php:112 | "basePriceForProduct stores max(price,": max(price,compare) charged as price; needs product confirm.
- [AUD:B1] ADOPTED high bug | checkout/src/Services/CheckoutService.php:78 | resumeCheckout unscoped find(); session enumeration.
- [AUD:B2] ADOPTED medium bug | checkout/src/Actions/CheckoutFinalizer.php:29 | raw transitionTo path; inconsistent vs transitionStatus().
- [AUD:B3] ADOPTED high bug | checkout/src/Models/CheckoutSession.php:183 | JSON blob read-modify-write, no lock; nested txn lengthens locks.
- [AUD:B4] ADOPTED medium security | checkout/src/Services/CheckoutService.php:51 | DUP R1:#2; no owner assertion. Counted once.
- [AUD:B5] ADOPTED medium bug | checkout/src/Steps/CreateOrderStep.php:156 | index-aligned pricing; join on item_id instead.
- [AUD:B6] ADOPTED medium bug | checkout/src/Steps/CreateOrderStep.php:326 | 'unknown' gateway/txn collapse idempotency key.
- [AUD:B7] ADOPTED low bug | checkout/src/Http/Controllers/PaymentCallbackController.php:188 | DUP R1:#10; FALSE-list residual type-segment. Counted once.
- [AUD:B8] ADOPTED high security | checkout/src/Models/CheckoutSession.php:80 | DUP R1:#3; fillable hijack/free-order. Counted once.
- [AUD:B9] ADOPTED medium security | checkout/src/Services/CheckoutService.php:51 | DUP R1:#2; ownerless session. Counted once.
- [AUD:B10] ADOPTED medium security | checkout/src/Http/Controllers/PaymentCallbackController.php:129 | DUP R1:#11; hit-before-verify. Counted once.
- [AUD:B11] ADOPTED low bug | checkout/src/Models/CheckoutSession.php:302 | intentional raw PK update; document only, no change.
- [AUD:B12] ADOPTED high perf | checkout/src/Services/CheckoutService.php:93 | DUP R1:#14; 20-30 UPDATEs per pipeline. Counted once.
- [AUD:B13] ADOPTED medium perf | checkout/src/Steps/CalculatePricingStep.php:194 | DUP R1:#8; 1 query/line. Counted once.
- [AUD:B14] ADOPTED medium perf | checkout/src/Services/CheckoutService.php:107 | txn wraps stepExecutor incl. gateway I/O; move I/O out.
- [AUD:Q#1] ADOPTED high bug | checkout/src/Services/CheckoutService.php:78 | DUP AUD:B1; queue row 8. Counted once.
- [AUD:Q#2] ADOPTED high security | checkout/src/Models/CheckoutSession.php:80 | DUP R1:#3; queue row 9. Counted once.
- [AUD:Q#3] ADOPTED high bug | checkout/src/Models/CheckoutSession.php:183 | DUP AUD:B3; lost-update queue row. Counted once.
- [AUD:Q#4] ADOPTED medium security | checkout/src/Services/CheckoutService.php:51 | DUP R1:#2; gap row from audit-cart.md. Counted once.

---

## chip

### E2E findings (verbatim)

E2E review of packages/chip (Collect + Send gateway, webhooks, local persistence).

POSITIVES (brief): production guard forbids disabling webhook signature verification; correct per-API algorithms (SHA256 Collect / SHA512 Send) with strict base64; log masking of PII/secrets with depth cap; purchase create reconciles response currency/total; minor-units money + currency cross-checks in builder/API; idempotency ledger uses fingerprint + hash_equals and requires lock-capable cache; no FK constraints, uuid PKs, manual purchase→payments cascade, no SoftDeletes; owner global scope (OwnerScope) + WebhookOwnerBatchRunner + batch-safe commands; raw SQL uses bindings (no injection); no unserialize/eval/shell/file ops (no deserialization/path-traversal surface); Actions used for purchase orchestration.

FINDINGS:

1. [HIGH | bug] Stale-cache swallows legitimate repeat refunds/captures — src/Services/Collect/PurchasesApi.php:238-294 (postMutation; callers refund 438-464, capture 482-500). postMutation caches mutation responses for 24h keyed only by operation+purchase+payload hash. Two genuine same-amount partial refunds (or captures) within TTL: the second returns the first cached response without any API call, so CHIP refunds once while the caller believes twice. Evidence: `$cache->put($cacheKey, [...response...], purchase_idempotency TTL 86400)` in postMutation; refund/capture route through it. Recommendation: cache only naturally-idempotent ops (cancel/release/resend/delete-recurring-token/mark-paid); for refund/capture require explicit caller idempotency keys or do not cache. Confidence: high.

2. [MEDIUM | security] Owner resolved from unsigned payload before signature check; brand oracle — src/Http/Controllers/WebhookController.php:29-47. Owner resolution + `$request->replace($payload)` with `__owner_*` run before Spatie signature validation (line 59). Unknown brand_id → 500 'Owner resolution failed'; valid brand + bad signature → 401 from the validator, letting anyone confirm a known brand_id and burning a DB lookup pre-auth. Owner is also resolved twice (lines 30, 69). Recommendation: verify signature first, then resolve owner; return a generic 401 for both failures; resolve once. Confidence: high (ordering verified; exploit value bounded since brand_ids are UUIDs).

3. [MEDIUM | bug] Idempotency reservations never expire and pollute analytics — src/Support/PurchaseIdempotencyLedger.php:16-100. reserve() writes a stub Purchase (status pending_execute, empty totals) and find() throws 'already reserved and requires reconciliation' whenever response is null, with no age/TTL check and no cleanup command — a crash between reserve() and record() bricks the key forever. Stubs also inflate pending counts in LocalAnalyticsService/WebhookMonitor-adjacent purchase metrics and can attach as localPurchase in EnrichedWebhookPayload. Recommendation: add reservation expiry (e.g. created_at window + cleanup command) and exclude stub rows (metadata flag/scope) from analytics. Confidence: high.

4. [MEDIUM | bug] chip:sync-from-api fatals when owner scoping is enabled — src/Actions/SyncChipRecordsFromApiAction.php:51. The `Purchase::query()->whereKey()->exists()` check sits outside try/catch and, with chip.owner.enabled and no console owner context, the global OwnerScope throws NoCurrentOwnerException (verified OwnerScope::apply → assertResolvedOrExplicitGlobal throws). The store path then fails per-ID inside try. Recommendation: run sync under explicit global/owner context or per-owner batching like WebhookOwnerBatchRunner, plus an --owner option. Confidence: high.

5. [MEDIUM | bug] Webhook dedup check-then-act race double-dispatches events — src/Webhooks/ProcessChipWebhook.php:30-50,97-110. isDuplicateWebhook() then dispatch+store is non-atomic; the unique idempotency_key column never guards it because the store path UPDATEs the Spatie row. Concurrent redelivery can dispatch PurchasePaid etc. twice. Recommendation: atomic insert-or-ignore keyed on idempotency_key (or DB transaction + lock) before dispatch. Confidence: med-high.

6. [MEDIUM | performance] Unbounded in-PHP aggregation loads full tables — src/Services/LocalAnalyticsService.php:201-227 (getRevenueTrend ->get() whole range), src/Webhooks/WebhookMonitor.php:18-40,84-108 (24h of webhooks into memory), src/Webhooks/WebhookRetryManager.php:93-104 (loads ALL failed webhooks, backoff eligibility filtered in PHP). OOM/slow on busy stores. Recommendation: SQL-side grouping/counts, WHERE last_retry_at eligibility in SQL, chunk/cursor pagination. Confidence: high.

7. [MEDIUM | bug] Refund-state sync has a lost-update race — src/Actions/Purchases/SyncPurchaseRefundState.php:44-70. Reads persisted refund sum + row, then forceFills cumulative refund_amount_minor with no transaction/row lock; concurrent refund webhooks (enabled by #5) undercount. Recommendation: DB::transaction + lockForUpdate on the purchase row. Confidence: med.

8. [MEDIUM | bug] Send webhooks have no owner attribution — src/Http/Controllers/SendWebhookController.php:19-43. Unlike the Collect controller, no brand/owner resolution and no OwnerContext wrapper; SendWebhookReceived (which has zero in-package listeners — host apps must listen) fires without context, so owner-enabled hosts throw or write global rows. Recommendation: mirror Collect owner resolution (brand map for Send) or document required host-side wrapping. Confidence: med (verified no listeners in chip/filament-chip; host impact depends on host listeners).

9. [LOW | bug] VerifyWebhookSignature middleware is dead code — src/Http/Middleware/VerifyWebhookSignature.php + src/ChipServiceProvider.php:169-176. Registered as singleton but never attached to any route (routes use only config middleware ['api']); Collect relies solely on the Spatie validator. Recommendation: wire it, alias it for hosts, or delete it. Confidence: high.

10. [LOW | bug] Public webhook routes have no explicit throttle — config/chip.php:113, src/ChipServiceProvider.php:62-66. Default middleware is just ['api']; anon CPU-heavy openssl verification + cold-cache public-key fetch (Cache::remember, no lock → stampede) depend on host's api group for throttling. Recommendation: add 'throttle:x,y' default and Cache::flexible/lock for public-key fetch. Confidence: med.

11. [LOW | bug] Over-permissive mass assignment on most models — src/Models/ChipIntegerModel.php:41-44, src/Models/ChipModel.php:42-45 ($guarded owner-only). SendInstruction/SendLimit/SendWebhook/BankAccount/Client/CompanyStatement/ChipCustomerLink leave every other column fillable (only Purchase/Payment/Webhook use $fillable). No in-package ::create($request) sink, so risk materializes via filament-chip/app code. Recommendation: convert to explicit $fillable. Confidence: high (fact) / low (exploitability in-package).

12. [LOW | bug] 32-bit int columns: Y2038 + money cap — database/migrations/2000_04_01_000001* (created_on/updated_on/viewed_on/due/paid_on as integer; total_minor/refund amounts as integer). Timestamps overflow in 2038; signed-int cents cap totals at ~RM21M. Recommendation: bigInteger/unsignedBigInteger for timestamps and money. Confidence: high.

13. [LOW | bug] public_key fetch bypasses rate-limit/retry/logging — src/Clients/ChipCollectClient.php:34-71. Direct Http call skips BaseHttpClient::request pipeline. Recommendation: route through request() with a raw-string variant. Confidence: high.

14. [LOW | performance] API rate-limit key is global, not per-owner — src/Clients/Http/BaseHttpClient.php:128-131 ('chip_api:'.class). One tenant's burst starves all tenants. Recommendation: include owner scope key when owner.enabled. Confidence: high.

15. [LOW | bug] Minor robustness nits — src/Data/EnrichedWebhookPayload.php:118 CarbonImmutable::parse on arbitrary string can throw (500 on malformed-but-signed payload; wrap in try); outbound endpoint interpolation "purchases/{$purchaseId}/..." (PurchasesApi find/cancel/refund/...) unsanitized — outbound-only, low risk; ChipSendService::createBankAccount has no input validation unlike createSendInstruction (API rejects, inconsistent). Confidence: med.

NOT FOUND (checked, clean): SQL injection (all raw SQL bound/constant), XSS sinks (no HTML rendering in-package), SSRF (redirect/callback URLs are merchant config passed to CHIP, never fetched; health-check endpoint dev-set), deserialization (none), path traversal (no file ops), Octane-unsafe static state (no mutable static props; singletons are stateless services), N+1 in hot paths (single-row lookups), unbounded pagination endpoints (none exposed), FK/cascade or SoftDeletes violations (none).

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### chip
Bugs:
- Float on minor units HIGH — `PurchaseDetailsData:96`, `ProductCollection:57,69`, `ProductData:105 (int)(amount*(float)qty)`; use half-up like `ChipIntegerModel:145`.
- `PurchasePaidHandler:27-29` MEDIUM — only `status=PAID`; never updates `paid_on/total_minor/payment_method`.
- `SyncPurchaseRefundState:56-63` MEDIUM — partial also `status=refunded` + `refunded_at` (no `partially_refunded` state).
- Refund sum-then-save race MEDIUM — no txn/lock.
- Duplicate in-flight webhooks both dispatch MEDIUM — `isDuplicate:97-110` only skips `processed`.
- `ChipCustomerDirectory:31-46` MEDIUM — `owner=null` adds no scope; callers must pass owner.
Security: verified safe — `verify_signature=false` throws in prod; secrets env + redaction; no server-side SSRF. `WebhookSimulator:154-156` disables verify test-only, acceptable.
Performance: indexes GOOD — `idempotency_key` unique, GIN metadata. No polling. Retry `usleep` is GET/HEAD/OPTIONS-only, never on mutations.

### Prior-audit fix-first rows
| 32 | chip | `Data/PurchaseDetailsData.php:96`, `ProductCollection.php:57,69`, `ProductData:105` | Float math on minor units, truncation | HIGH |

### Verification verdicts

- [R1:#1] CONFIRMED high bug | packages/chip/src/Services/Collect/PurchasesApi.php:238 | "Stale cache refunds" 24h op+purchase+payload key; repeat same-amount refund returns stale, no API call
- [R1:#2] CONFIRMED medium security | packages/chip/src/Http/Controllers/WebhookController.php:29 | "Owner before signature" resolve+500 pre-auth vs 401 oracle; resolved twice :30/:69
- [R1:#3] CONFIRMED medium bug | packages/chip/src/Support/PurchaseIdempotencyLedger.php:16 | "Reservations never expire" no TTL/cleanup; crash bricks key; stubs inflate pending_execute counts
- [R1:#4] CONFIRMED medium bug | packages/chip/src/Actions/SyncChipRecordsFromApiAction.php:51 | "Sync fatals scoped" exists() outside try; OwnerScope throws with no console owner context
- [R1:#5] CONFIRMED medium bug | packages/chip/src/Webhooks/ProcessChipWebhook.php:33 | "Dedup race dispatches" check-then-act; UPDATE path defeats unique key. DUP AUD:B5
- [R1:#6] CONFIRMED medium performance | packages/chip/src/Services/LocalAnalyticsService.php:204 | "Unbounded PHP aggregation" revenue/monitor/retry load full sets, filter in PHP
- [R1:#7] CONFIRMED medium bug | packages/chip/src/Actions/Purchases/SyncPurchaseRefundState.php:42 | "Refund lost update" sum-then-forceFill without txn/lock. DUP AUD:B4
- [R1:#8] CONFIRMED medium bug | packages/chip/src/Http/Controllers/SendWebhookController.php:19 | "Send unattributed webhooks" no owner resolve/context; zero in-package listeners
- [R1:#9] CONFIRMED low bug | packages/chip/src/ChipServiceProvider.php:171 | "Dead signature middleware" singleton never attached; routes use ['api'] only
- [R1:#10] CONFIRMED low bug | packages/chip/config/chip.php:113 | "No webhook throttle" default ['api']; no explicit throttle:x,y in package
- [R1:#11] CONFIRMED low bug | packages/chip/src/Models/ChipModel.php:42 | "Permissive mass assignment" $guarded owner-only; risk via filament/app create paths
- [R1:#12] CONFIRMED low bug | packages/chip/database/migrations/2000_04_01_000001_create_chip_purchases_table.php:21 | "32-bit int columns" unix-time/money ints; Y2038 overflow + ~RM21M cap
- [R1:#13] CONFIRMED low bug | packages/chip/src/Clients/ChipCollectClient.php:36 | "Public-key bypasses pipeline" direct Http skips retry/rate-limit/logging wrapper
- [R1:#14] CONFIRMED low performance | packages/chip/src/Clients/Http/BaseHttpClient.php:128 | "Global rate-limit key" chip_api:class shared across all tenants
- [R1:#15] CONFIRMED low bug | packages/chip/src/Data/EnrichedWebhookPayload.php:118 | "Robustness nits trio" parse can throw; outbound id interpolation; bank-acct unvalidated
- [AUD:B1] ADOPTED high bug | packages/chip/src/Data/PurchaseDetailsData.php:96 | Float qty math on minor units truncates; use half-up minor-unit rounding
- [AUD:B2] ADOPTED medium bug | packages/chip/src/Webhooks/Handlers/PurchasePaidHandler.php:27 | Paid handler sets status only; paid_on/total_minor/method stay stale
- [AUD:B3] ADOPTED medium bug | packages/chip/src/Actions/Purchases/SyncPurchaseRefundState.php:56 | Partial refunds marked fully refunded; no partially_refunded state kept
- [AUD:B4] ADOPTED medium bug | packages/chip/src/Actions/Purchases/SyncPurchaseRefundState.php:42 | DUP R1:#7; merged, counted once
- [AUD:B5] ADOPTED medium bug | packages/chip/src/Webhooks/ProcessChipWebhook.php:97 | DUP R1:#5; merged, counted once
- [AUD:B6] ADOPTED medium bug | packages/chip/src/Services/ChipCustomerDirectory.php:31 | owner=null adds no explicit scope; callers must pass owner
- [AUD:Q#32] ADOPTED high bug | packages/chip/src/Data/PurchaseDetailsData.php:96 | DUP AUD:B1; queue row merged, counted once

---

## commerce-support

### E2E findings (verbatim)

End-to-end review: packages/commerce-support (foundation: owner scoping, webhooks, targeting, money, reference data). Inspected: config, all 7 models, OwnerContext/OwnerScope/HasOwner/OwnerQuery/OwnerRouteBinding/OwnerWriteGuard/OwnerCache/OwnerFilesystem/OwnerBatchRunner/OwnerSignedDownload/OwnerJobContext, Resolve* actions, ProcessWebhookCallAction, webhook processor/validator/profile, PinnedHttpClient/PublicHttpUrlGuard/SystemPublicDnsResolver, TargetingEngine + context + evaluators, MoneyNormalizer/MoneyFormatter, Filament widget/navigation/pages, all 12 migrations, seed actions, Setup/Install/Boost/Publish commands, UpsertEnv/Symlink/ProjectRoot actions, HasCommerceAudit/LogsCommerceActivity, helpers, service provider, health blade.

FINDINGS

1. [high/security] packages/commerce-support/src/Models/Report.php:47 — Report mass-assignment of privileged + polymorphic fields
Fillable includes reportable_type/id, reporter_type/id, reviewed_by_type/id, status, severity, resolution, internal_notes. Any ::create($input)/fill() path lets a caller file a report as another user, self-assign reviewer, and set status=resolved/severity. Same pattern in SavedSearch.php:34 (user_type/user_id, searchable_type/id fillable, no HasOwner) and NotificationPreference.php:37 (user_type/user_id fillable). Evidence: `protected $fillable = ['reportable_type','reportable_id','reporter_type','reporter_id','report_type','status','severity',... 'reviewed_by_type','reviewed_by_id',...]`. Recommendation: remove *_type/*_id and workflow fields (status/severity/reviewed_*/resolution/internal_notes) from $fillable; set them server-side from auth context + explicit transitions. Confidence: high.

2. [high/bug] packages/commerce-support/src/Actions/ProcessWebhookCallAction.php:29 — Webhook dedup race + long lock hold
Dedup check (isDuplicateProcessedEvent, a plain exists() in CommerceWebhookProcessor.php:107) runs inside the same lockForUpdate transaction as processEvent. Two concurrent deliveries of the same event_id can both pass exists() before either commits → double processing (double charge/fulfill). Also processEvent (arbitrary side effects) runs while holding the row lock → lock contention/timeouts. Evidence: `DB::transaction(... lockForUpdate ... if duplicate...; $processEvent($eventType,$payload); $locked->update(...))`. Recommendation: enforce a UNIQUE(name, event provider id) column or advisory lock/psql upsert on (name, event_id) before processing; move processEvent outside the row-lock transaction (claim-then-process with status=processing). Confidence: high.

3. [medium/security] packages/commerce-support/src/Actions/ProcessWebhookCallAction.php:65 — Full exception string persisted to webhook_calls
Catch block stores `(string) $e` (message + trace + paths, often SQL/fragments of payload) into the `exception` text column, readable by anyone with DB/webhook-read access. Evidence: `'exception' => (string) $e`. Recommendation: store only class + message truncated (e.g. Str::limit($e->getMessage(), 2000)), log full trace server-side. Confidence: high.

4. [medium/security] packages/commerce-support/src/Targeting/TargetingContext.php:303 — Promotion targeting trusts client-spoofable headers
getChannel() trusts X-Channel/X-Sales-Channel, getCountry() trusts CF-IPCountry/X-Country/X-Geo-Country, getReferrer() trusts Referer, falling back to metadata the caller supplies. A shopper setting headers/UTM can steer channel/country/device rules to unlock vouchers. Evidence: `$this->request->header('X-Channel') ?? $this->request->header('X-Sales-Channel')`. Recommendation: document as untrusted input; resolve channel/country server-side (session/storefront config, verified geo-IP) for discount-gating rules; treat header-derived values as hints only. Confidence: med.

5. [medium/security] packages/commerce-support/src/Support/OwnerFilesystem.php:53 — Traversal check is a substring block, bypassable
`str_contains($relativePath,'..') || str_starts_with('/'` misses encoded variants (`..%2f`, `.%2e/`), backslash/absolute Windows paths (`C:\`, `\foo`), and does not normalize `./` or resolve symlinks; disk is the unqualified default (could be public). Evidence lines 60-66. Recommendation: normalize (rawurldecode repeatedly, unify separators), reject any segment === '..' or '' after split, reject absolute/drive-letter paths, and pin to an explicit private disk or allowlist. Confidence: med.

6. [medium/bug] packages/commerce-support/src/Support/OwnerContext.php:160 — fromTypeAndId() builds phantom owners without existence check
Instantiates any Eloquent-resolving owner_type with the given id and returns it as the owner context; used by OwnerBatchRunner/ParsedOwnerTuple/OwnerUiScope/OwnerJobContext. Orphaned or poisoned (see finding 1) owner tuples yield a working "owner" scope instead of failing. Evidence: `$owner = new $resolved; $owner->setAttribute(...); return $owner;`. Recommendation: add an opt-in `fromTypeAndIdOrFail` that actually queries (exists/select) and use it on trust boundaries (jobs, route binding, batch runner). Confidence: med.

7. [medium/performance] packages/commerce-support/database/migrations/1970_01_01_000004_create_webhook_calls_table.php.stub — webhook_calls has zero indexes
No index on name/status/processed_at, yet every delivery runs the JSON-path dedup query (payload->event_id/id + payload->event_type + name + processed_at). Full scans that degrade linearly with table growth. Recommendation: add composite index (name, processed_at) plus a stored/generated event-id column with unique index (also fixes finding 2). Confidence: high.

8. [medium/performance] packages/commerce-support/src/Support/OwnerBatchRunner.php:87 — Unbounded distinct owner-tuple load
`DB::table(...)->select(...)->distinct()->get()` loads every owner tuple into memory; no chunking. Malformed tuples throw mid-run after partial callbacks (no atomicity). Evidence lines 92-96 + collectForOwners loop. Recommendation: chunk/distinct-cursor the tuple scan; validate tuples before running any callback. Confidence: high.

9. [medium/bug] packages/commerce-support/src/Support/OwnerCache.php:126 — forgetOwner() silently no-ops on non-taggable drivers; remember() has no stampede guard
Tag flush is wrapped in try/catch(Throwable){} so file/database/array drivers silently keep stale owner keys; remember() has no lock → thundering herd on expensive callbacks. Evidence lines 130-146. Recommendation: fail loudly or track an owner version key for invalidation on drivers without tags; use Cache::lock around remember() rebuilds. Confidence: high.

10. [medium/performance] packages/commerce-support/src/Targeting/TargetingContext.php:135,279 — N+1 in targeting evaluation
isFirstPurchase() runs `$user->orders()->count()` per evaluation with no memoization (repeated across rules); getProductCategories() lazy-loads `categories` per cart line. Evidence lines 135-136, 279-285. Recommendation: memoize counts/segments per TargetingContext instance; eager-load categories once per evaluation. Confidence: med-high.

11. [medium/bug] packages/commerce-support/src/Concerns/LogsCommerceActivity.php:74 + HasCommerceAudit.php:175 — Activity/audit logs capture fillable/PII by default
Loggable attributes default to `$this->fillable` (includes *_type/*_id and whatever the model fills), and the audit sensitive-field list omits email/phone/address/postcode/name/dob. Evidence: `return $this->fillable;` and the 10-item sensitive list. Recommendation: default loggable to an explicit per-model allowlist and extend sensitive fields (or invert: exclude PII unless allowlisted). Confidence: med.

12. [low-medium/bug] packages/commerce-support/src/Support/MoneyNormalizer.php:32 — Float path violates minor-units rule
toDollars()/format() do `$cents/100` float division (display drift, e.g. large values) and MoneyFormatter decimal paths also divide in float before number_format. Evidence lines 32-50. Recommendation: keep integer math until the final string (intdiv + str_pad) for display; keep float helpers clearly labelled display-only. Confidence: high (impact med).

13. [low/bug] packages/commerce-support/src/Filament/Widgets/CommerceHealthWidget.php:57 + resources/views/widgets/health-status.blade.php:6,21,46 — Health results loaded 3x per render; preg_split unchecked
Blade calls getOverallStatus(), getStatusCounts(), getHealthResults(), each re-running latestResults(); formatCheckName() (line 185) passes preg_split() result directly to implode (false → TypeError). Recommendation: memoize getHealthResults() per request; guard `preg_split(...) ?: []`. Confidence: high.

14. [low/performance] packages/commerce-support/src/Actions/SeedCurrenciesAction.php:25, SeedTimezonesAction.php:25, SeedLanguagesAction.php:27 — Reference-data seeding is N+1 without a transaction
Per-row select + insert/update (~2 queries x ~150+ rows), no transaction → slow and partially applied on failure. Recommendation: wrap in DB::transaction and use upsert() keyed by code/name. Confidence: high.

15. [low/bug] packages/commerce-support/src/Targeting/TargetingEngine.php:156 — Unbounded expression recursion (DoS)
evaluateExpression()/validateExpression() recurse on merchant/admin-supplied and/or/not nesting with no depth or node cap → deep payload can exhaust stack. Recommendation: enforce max depth (e.g. 10) and max node count in validate() before evaluating. Confidence: med.

16. [low/security] packages/commerce-support/src/Http/PinnedHttpClient.php:28 — DNS-pin only enforced on curl transports
CURLOPT_RESOLVE pinning is skipped silently for IP literals (fine) but throws only when curl missing; non-curl HTTP drivers would ignore `curl` options, losing the DNS-rebinding protection PublicHttpUrlGuard validated. Recommendation: assert the HTTP client is curl-backed (or refuse to send) when a resolve entry is required. Confidence: low-med.

17. [low/bug] packages/commerce-support/src/Actions/UpsertEnvVariablesAction.php:19 — Non-atomic .env rewrite
File::get → File::put with no lock/backup; duplicate keys leave stale earlier lines; every value is force double-quoted (changes `true`/numeric semantics). Recommendation: file lock + backup, collapse duplicates, preserve quoting style when value needs no quoting. Confidence: med.

18. [low/performance] packages/commerce-support/src/Traits/HasOwner.php:334 — getOwnerDisplayNameAttribute lazy-loads owner per row
`$this->owner` per model → N+1 in any list rendering the attribute. Recommendation: document eager-loading (`with('owner')`) or cache per (type,id) per request. Confidence: high.

19. [low/bug] taggables morph-id type is fixed UUID (1970_01_01_000001 stub) while commerce morph keys may be int/ulid → tagging int-PK models breaks; several 2025_* stubs have no down(). Recommendation: use commerce_morph_key() for taggable_id; add down() methods. Confidence: med.

POSITIVES (brief)
- Owner isolation is well-architected: request-attribute storage + Octane flush (OwnerContext), global scope fail-closed without context, HasOwner blocks promotion/demotion/reassignment, OwnerUiScope/OwnerScopedIds fail closed, OwnerScopeKey hashes scope keys, NeedsOwner fails closed.
- SSRF guard is strong: PublicHttpUrlGuard enforces http(s), no creds/fragments, standard ports, FQDN + DNS→public-IP-only validation (incl. NAT64/multicast checks), pinned transport with redirects disabled.
- Webhook security basics right: hash_equals HMAC validation, lockForUpdate idempotency attempt, transactional status updates.
- Repo-rule compliance observed: uuid PKs, no FK constraints/cascades in migrations, no SoftDeletes, Actions for orchestration, money stored int minor units, JsonDisplay escapes output, health widget gated by Gate ability, TargetingEngine fails closed on invalid rules.
- No eval/shell/deserialization-of-input, no raw user input in SQL (single whereRaw is constant '1 = 0'), no {!! !!} unescaped output in package blade.

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### commerce-support
Bugs:
- `src/Support/MoneyNormalizer.php:32` LOW (was MEDIUM) — `toDollars(int): float` display helper only; do not use for persist/calc.
- `src/Support/MoneyNormalizer.php:45` LOW — `format()` returns `formatCurrency()` typed `string`, no `false` check → TypeError on failure.
- `src/Actions/ProcessWebhookCallAction.php:65-69` LOW — failure `update(exception=(string)$e)` outside txn, unbounded (DB bloat / secret leak).
- `src/Models/Tag.php:10` LOW — no `getTable()`, breaks prefix contract.
Security:
- `src/Webhooks/CommerceSignatureValidator.php:71-93` LOW (was MEDIUM) — `hash_equals` + `hmac` correct; no timestamp/nonce/replay, no `sha256=` strip. Base-class standard.
- `src/Models/Report.php:47-56` MEDIUM — `status/reviewed_by_*/timestamps/internal_notes` fillable → forge reviewer + terminal timestamps.
Performance:
- `src/Support/OwnerBatchRunner.php:92-95` MEDIUM — `distinct()->get()` loads all owner tuples; chunk it.
- `src/Support/OwnerBatchRunner.php:113,138` LOW (was MEDIUM) — global `config(include_global)` flip per-run with `finally` restore; correct but Octane-fragile.
- `SeedLanguagesAction:34-52` LOW — per-row `where(code)->first()` + insert/update (seed-only, small N).

### Verification verdicts

- [R1:#1] DOWNGRADED (was high) medium security | packages/commerce-support/src/Models/Report.php:47 | "Report mass assignment" fillable morph+workflow fields; adopt MEDIUM. DUP AUD:B6
- [R1:#2] CONFIRMED high bug | packages/commerce-support/src/Actions/ProcessWebhookCallAction.php:29 | "Webhook dedup race" per-row lock; concurrent same-event both pass; effects under lock
- [R1:#3] DOWNGRADED (was medium) low security | packages/commerce-support/src/Actions/ProcessWebhookCallAction.php:65 | "Exception string persisted" (string)$e trace to DB; adopt LOW. DUP AUD:B3
- [R1:#4] CONFIRMED medium security | packages/commerce-support/src/Targeting/TargetingContext.php:303 | "Spoofable targeting headers" X-Channel/CF-IPCountry/Referer steer channel/country rules
- [R1:#5] CONFIRMED medium security | packages/commerce-support/src/Support/OwnerFilesystem.php:60 | "Traversal check bypassable" substring '..' misses encoded/backslash/drive paths; default disk
- [R1:#6] CONFIRMED medium bug | packages/commerce-support/src/Support/OwnerContext.php:160 | "Phantom owner contexts" fromTypeAndId never queries; orphaned tuples yield scope
- [R1:#7] CONFIRMED medium performance | packages/commerce-support/database/migrations/1970_01_01_000004_create_webhook_calls_table.php.stub:22 | "Webhook_calls unindexed" zero indexes; JSON-path dedup scans full table
- [R1:#8] CONFIRMED medium performance | packages/commerce-support/src/Support/OwnerBatchRunner.php:92 | "Unbounded owner tuples" distinct()->get() all in memory. DUP AUD:B7
- [R1:#9] CONFIRMED medium bug | packages/commerce-support/src/Support/OwnerCache.php:126 | "Cache invalidation no-ops" tag flush swallowed on untaggable drivers; remember() herd
- [R1:#10] CONFIRMED medium performance | packages/commerce-support/src/Targeting/TargetingContext.php:135 | "Targeting N+1 queries" orders()->count() + per-line categories, unmemoized
- [R1:#11] CONFIRMED medium bug | packages/commerce-support/src/Concerns/LogsCommerceActivity.php:74 | "Logs capture fillable" loggable defaults to fillable; sensitive list omits email/phone/name
- [R1:#12] DOWNGRADED (was low-medium) low bug | packages/commerce-support/src/Support/MoneyNormalizer.php:32 | "Float minor-units path" cents/100 float; display-only per audit. DUP AUD:B1
- [R1:#13] CONFIRMED low bug | packages/commerce-support/src/Filament/Widgets/CommerceHealthWidget.php:57 | "Health rendered thrice" 3 getters re-run latestResults; preg_split unguarded :185
- [R1:#14] CONFIRMED low performance | packages/commerce-support/src/Actions/SeedCurrenciesAction.php:25 | "Seeding N+1 rows" per-row select+save x150+, no txn/upsert. DUP AUD:B9
- [R1:#15] CONFIRMED low bug | packages/commerce-support/src/Targeting/TargetingEngine.php:156 | "Unbounded expression recursion" and/or/not recurse sans depth cap in eval+validate
- [R1:#16] CONFIRMED low security | packages/commerce-support/src/Http/PinnedHttpClient.php:28 | "Curl-only DNS pin" CURLOPT_RESOLVE ignored on non-curl drivers
- [R1:#17] CONFIRMED low bug | packages/commerce-support/src/Actions/UpsertEnvVariablesAction.php:19 | "Non-atomic env rewrite" get-put no lock/backup; dup keys stale; forced quotes
- [R1:#18] CONFIRMED low performance | packages/commerce-support/src/Traits/HasOwner.php:334 | "Owner name N+1" $this->owner lazy-load per row in accessor
- [R1:#19] CONFIRMED low bug | packages/commerce-support/database/migrations/1970_01_01_000001_create_tag_tables.php.stub:31 | "Taggables UUID fixed" int-PK tagging breaks; 2025_* + tags stubs lack down()
- [AUD:B1] ADOPTED low bug | packages/commerce-support/src/Support/MoneyNormalizer.php:32 | DUP R1:#12; merged, counted once
- [AUD:B2] ADOPTED low bug | packages/commerce-support/src/Support/MoneyNormalizer.php:45 | formatCurrency false into string return; add false check
- [AUD:B3] ADOPTED low bug | packages/commerce-support/src/Actions/ProcessWebhookCallAction.php:65 | DUP R1:#3; merged, counted once
- [AUD:B4] ADOPTED low bug | packages/commerce-support/src/Models/Tag.php:10 | No getTable() override; breaks prefix contract
- [AUD:B5] ADOPTED low security | packages/commerce-support/src/Webhooks/CommerceSignatureValidator.php:71 | hmac+hash_equals ok; no replay/sha256= strip
- [AUD:B6] ADOPTED medium security | packages/commerce-support/src/Models/Report.php:47 | DUP R1:#1; merged, counted once
- [AUD:B7] ADOPTED medium performance | packages/commerce-support/src/Support/OwnerBatchRunner.php:92 | DUP R1:#8; merged, counted once
- [AUD:B8] ADOPTED low bug | packages/commerce-support/src/Support/OwnerBatchRunner.php:113 | Global include_global flip w/ finally; correct but Octane-fragile
- [AUD:B9] ADOPTED low performance | packages/commerce-support/src/Actions/SeedLanguagesAction.php:34 | DUP R1:#14; merged, counted once

---

## communications

### E2E findings (verbatim)

End-to-end review of packages/communications — findings (severity / category / file:line):

1. HIGH / bug — src/Actions/CreateTrackingTokenAction.php:28-47 — getToken() returns the hash, raw token is lost. `Str::random(64)` is hashed and only `token_hash` persisted; `getToken()` returns `$token->token_hash`. Callers can never build a working tracking URL (lookup by hash requires the raw token). Evidence: `$token = Str::random(64); $tokenHash = hash('sha256', $token);` … `public function getToken(...): string { return $token->token_hash; }`. Fix: return `[$trackingToken, $token]` (or a DTO) from `handle()` and drop/getToken-raw confusion. Confidence: high.

2. HIGH / security+bug — src/Actions/CreateTrackingTokenAction.php:35-36 — target URL stored plaintext in `target_url_ciphertext`, unvalidated (SSRF/open-redirect). No encryption despite column name; no scheme/host allowlist, so `javascript:`/`file:`/internal URLs persist; `parse_url(..., PHP_URL_HOST)` can return false into a string column. Fix: encrypt via DestinationProtector, validate http(s) scheme + host allowlist, handle parse failure. Confidence: high.

3. HIGH / security — src/Support/DestinationProtectorService.php:12-32 — unauthenticated AES-256-CBC + unsafe decrypt. No HMAC means ciphertext is malleable (bit-flipping); `decrypt()` uses non-strict `base64_decode`, no length checks, and `openssl_decrypt()` can return `false` against a `string` return type (TypeError / info leak). Fix: use Laravel `Crypt` (authenticated) or add encrypt-then-MAC with strict decode and `false` handling. Confidence: high.

4. HIGH / bug — src/Services/CommunicationRecorderService.php:116-133 — status recalculation compares enum objects to strings, always falls to Processing. `$d->status` is a cast `DeliveryStatus` enum, but `in_array($d->status, ['sent',...], true)` and `$d->status === 'failed'` use strict string comparison → `$allSent`/`$anyFailed` always false, Completed/Failed never set via markSending/markSent/markFailed path. Fix: compare `$d->status->value` (as RecalculateCommunicationStatusAction does). Confidence: high.

5. HIGH / bug — src/Actions/ApplyProviderEventAction.php:84-92 + src/Console/Commands/ReplayWebhookEventsCommand.php:146-157 — provider events bypass the delivery state machine and event dispatch. Direct `$delivery->status = EVENT_STATUS_MAP[...]` allows illegal regressions (e.g. failed/cancelled → opened), skips TransitionDeliveryAction validation and Delivery* domain events, unlike the normal path. Fix: route through TransitionDeliveryAction (with explicit force/allowlist for out-of-order provider events) so transitions stay legal and observable. Confidence: high.

6. HIGH / bug — src/Webhooks/ConfigWebhookOwnerResolver.php:12-15 + src/Actions/ApplyProviderEventAction.php:73-76 — default webhook owner resolver always returns null, so provider events run in global owner scope and OwnerWriteGuard (throws AuthorizationException cross-scope) fails for owner-scoped deliveries. Webhook ingestion is broken for multi-tenant data until a custom resolver is bound. Fix: document as required integration + resolve owner from delivery/communication (include-global read then scope), or fail closed with a clear error. Confidence: med (guard semantics confirmed: AuthorizationException on cross-scope).

7. MEDIUM / bug — src/Services/CommunicationManagerService.php:34-43 — idempotency check-then-act race + lock never released on failure. `exists()` then `acquire()` lets two concurrent callers both pass; if dispatcher throws, the `Cache::add` lock stays for full TTL blocking legitimate retry. Fix: use only `acquire()`'s atomic result, release in catch/finally on failure. Confidence: high.

8. MEDIUM / performance+bug — src/Actions/PlanCommunicationDeliveriesAction.php:34-72 — unbounded per-item queries inside a transaction. Each planned delivery does a recipient query + content-exists query; no cap on `$planned`, long-held transaction; `CarbonImmutable::parse($plan->scheduledAt)` throws uncaught on malformed input. Fix: cap batch size, preload recipients/contents by id, validate dates in PlannedDeliveryData. Confidence: high.

9. MEDIUM / performance — src/Jobs/DispatchCommunicationDeliveriesJob.php:44-54 — unbounded `->get()` + per-row transition. Loads all queued deliveries into memory, one save + events each. Fix: `chunkById`/`cursor` with `lockForUpdate`/`skipLocked` for concurrent workers. Confidence: high.

10. MEDIUM / performance+bug — src/Models/CommunicationDelivery.php:203-207 — `deleting` hook uses unbounded `->each()` on attempts/events/tokens (loads all into memory); parent Communication cascade (Communication.php:223-240) issues per-model deletes with no transaction and orphans `children` (parent_id never cleared). Fix: chunk deletes in a transaction, null/reassign children. Confidence: high.

11. MEDIUM / performance — src/Console/Commands/DispatchDueCommunicationsCommand.php:69-123 — count() then re-query chunk, per-communication unbounded deliveries `->get()` (N+1), per-row saves bypassing transition validation, one job dispatched per row (queue storm). Fix: chunk once, bulk-update delivery statuses via validated transition set, dispatch fewer batched jobs. Confidence: high.

12. MEDIUM / security — src/Http/Controllers/WebhookController.php:25-37 — unbounded JSON payload accepted into a queued job. No size/depth limit before `ProcessWebhookEventJob::dispatch(payload: $payload)` → memory/queue exhaustion via large bodies (throttle limits rate, not size). Fix: enforce max body size (e.g. 256KB–1MB) and payload depth/key limits; reject oversize with 413. Confidence: med.

13. MEDIUM / bug — src/Actions/RecordTrackingInteractionAction.php:20-48 — no expiry/revocation check, unvalidated interaction type. Expired/revoked tokens still record events and bump usage; free-form `$interactionType` pollutes event taxonomy. Fix: reject expired/revoked tokens, enum-constrain interaction type. Confidence: high.

14. MEDIUM / bug — src/Actions/DispatchManagedNotificationAction.php:78-89 — unsafe notifiable handling + config bypass. `$notifiable::class`/`getKey()` with no is-object check (TypeError on scalar); stores FQCN instead of `getMorphClass()` (breaks custom morph maps, inconsistent with the rest of the package); `via()` channels unvalidated; `max_attempts = 3` hardcoded ignoring `communications.defaults.max_attempts`. Fix: validate notifiable, use `getMorphClass()`, allowlist channels, read config. Confidence: high.

15. MEDIUM / security — src/Actions/ReceiveInboundCommunicationAction.php:86-91 — inbound content stored unredacted while outbound is redacted. Subject/body/metadata redaction is inconsistent (metadata redacted, body/subject raw) → secrets/PII in inbound mail retained verbatim. Also `$fromType`/`$fromId` morph strings unvalidated. Fix: redact/store per policy, validate or resolve sender morphs. Confidence: med.

16. LOW / security — src/Http/Middleware/VerifyWebhookSignature.php:29-37 — provider enumeration via status codes (unknown provider → 404, known-without-secret → 401); fixed `X-Webhook-Signature`/HMAC-SHA256 with no per-provider algorithm. Fix: uniform 401s, per-provider algorithm config. Confidence: med.

17. LOW / bug — src/Jobs/ProcessWebhookEventJob.php:89-97 — fingerprint uses `md5(json_encode(...))` without `JSON_THROW_ON_ERROR`; `json_encode` returning false collapses distinct payloads to one fingerprint → webhooks wrongly deduped; full payload hashed per job (cost scales with finding 12). Fix: throw on encode failure, hash canonical subset + provider event id. Confidence: med.

18. LOW / bug — src/Console/Commands/ExpireCommunicationsCommand.php:71-74 — expiring overwrites `expires_at` with now(), destroying the original deadline; bulk `update()` skips model events and leaves deliveries unexpired. Fix: set status + `expired_at`/touch only, expire pending deliveries. Confidence: high.

19. LOW / bug — src/Console/Commands/PruneCommunicationDataCommand.php:60-62 (+ PruneNotificationInboxesCommand:28) — `--before` parsed with `CarbonImmutable::parse` uncaught → invalid date crashes command with stack trace. Fix: try/catch with validation error. Confidence: high.

20. LOW / bug — src/Actions/ResolveCommunicationThreadAction.php:26-41 — check-then-create race on (channel, external_thread_id); migration has only a non-unique index, so concurrent inbound creates duplicate threads. Fix: unique index + `firstOrCreate`/upsert handling. Confidence: med.

21. LOW / bug — src/Actions/StartDeliveryAttemptAction.php:22-33 — `attempt_count` read-modify-write without lock; concurrent starts duplicate `attempt_number` (index is non-unique). Fix: `lockForUpdate` on delivery or DB increment. Confidence: med.

22. LOW / performance — src/Actions/RecalculateCommunicationStatusAction.php:21 + NotificationInboxService.php:131-137 — `get(['status'])` loads all deliveries (ok for small sets, unbounded in general); `prune()` does `count()` then full `delete()` in one statement (long lock). Fix: chunk/pluck statuses; chunk deletes. Confidence: med.

23. LOW / bug — src/Services/NotificationInboxService.php:192-198 — `validateOwnedModel()` silently swallows InvalidArgumentException (thrown for non-owner-aware model classes), so recipients without HasOwner skip the cross-owner check entirely. Fix: explicitly allowlist skippable classes or log; don't swallow in a `validate*` method. Confidence: med.

24. LOW / bug — src/Actions/RecordProviderEventAction.php:81-88 + ApplyProviderEventAction.php:106-113 — duplicate provider-event check is exists()-then-insert; races surface as raw QueryException instead of the intended RuntimeException despite the unique index. Fix: catch unique-violation and rethrow domain exception. Confidence: med.

25. LOW / correctness — src/Policies/CommunicationPolicy.php — only view/viewAny/create/cancel/retry defined; update/delete/updateAny missing (deny by default) may break Filament edit flows in filament-communications; viewAny/create always true relies solely on query scoping. Confirm intended with the Filament adapter. Confidence: low.

26. LOW / correctness — src/Listeners/AutoCaptureNotificationListener.php:61-95 — AutoCaptureState keyed by `spl_object_id()` can collide if a notification object is GC'd and its id reused within the same request (mis-attributed deliveries); registry never pruned (bounded per-request via scoped binding — Octane-safe, but note it). Fix: unset state in handleSent / use SplObjectStorage. Confidence: low.

Positives (verified): all 17 models use HasOwner + HasOwnerScopeConfig with uuid PKs, no FK constraints/cascades in migrations (foreignUuid without constrained), no SoftDeletes, money as int `cost_minor`; `owner_type/owner_id` excluded from `$fillable` (auto-assign); webhook HMAC uses `hash_equals` + timestamp tolerance + per-provider secrets + rate limiting; CommunicationDestination `address` uses `encrypted` cast; tracking tokens store only sha256 hash with unique index; provider-event unique index exists; migrations carry sensible composite indexes (owner+status, provider+message, recipient+channel); inbox Livewire scopes by recipient, paginates (15), escapes output in Blade; jobs/commands consistently use OwnerContext/OwnerContextJob traits; singletons hold no request state (Octane-safe); no mass-assignment, SQL-injection (no raw SQL except constant `1 = 0`), XSS (`{{ }}` only), deserialization, or path-traversal vectors found.

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### communications (17 models)
Bugs:
- Tracking token HIGH — `CreateTrackingTokenAction:26-47` discards plaintext (`Str::random(64)`), persists only hash; `getToken()` returns hash-as-bearer; no redemption route.
- Inbound `findOrFail` without guard HIGH — `CreateTrackingTokenAction:26`, `AddCommunicationRecipientAction:25`, `RecordTrackingInteractionAction:26-28` trust global scope.
- `Communication:221-243` + `Delivery:201-208` MEDIUM — `chunkById(100)->each(delete)` ×6 with nested cascades → 1000s queries.
Security:
- Inbound IDs without guard HIGH (above).
- Attachment `disk/path/mime` mass-assignable MEDIUM-verify — `Attachment:47-60` fillable; no in-`src/` `mimes/max` (Filament-only = API bypass). Confirm server validation.
- `DestinationProtectorService:12-32` AES-CBC unauthenticated LOW-verify — confirm or use AEAD.
- Webhook ingress GOOD — HMAC+`hash_equals`+300s+abort-on-missing-secret; `__owner_*` strip + server re-resolve.
Performance: GOOD — queued webhooks/deliveries/notifications; `202` dispatch; configurable idempotency store. Delete amplification (bugs) is the perf cost.

### Prior-audit fix-first rows
| 16 | communications | `Actions/CreateTrackingTokenAction.php:26-47` | Plaintext token discarded, `getToken()` returns hash; no bearer route | HIGH |
| 17 | communications | `AddCommunicationRecipientAction`, `RecordTrackingInteractionAction` | `findOrFail` without `OwnerWriteGuard` | HIGH |

### Verification verdicts

- [R1:#1] CONFIRMED high bug | packages/communications/src/Actions/CreateTrackingTokenAction.php:28 | "Token hash returned" raw token lost; getToken leaks hash as bearer. DUP AUD:B1
- [R1:#2] CONFIRMED high security | packages/communications/src/Actions/CreateTrackingTokenAction.php:35 | "Plaintext target URL" unencrypted, unvalidated scheme/host; parse_url false risk
- [R1:#3] DOWNGRADED (was high) low security | packages/communications/src/Support/DestinationProtectorService.php:12 | "Unauthenticated AES-CBC" no MAC; lax decode; adopt LOW. DUP AUD:B6
- [R1:#4] CONFIRMED high bug | packages/communications/src/Services/CommunicationRecorderService.php:122 | "Enum-string status compare" strict string checks on enum always false; stuck Processing
- [R1:#5] CONFIRMED high bug | packages/communications/src/Actions/ApplyProviderEventAction.php:84 | "State machine bypassed" direct writes allow regressions, skip Delivery* events
- [R1:#6] CONFIRMED high bug | packages/communications/src/Webhooks/ConfigWebhookOwnerResolver.php:12 | "Null owner resolver" default null; global scope breaks owner-scoped guard lookup
- [R1:#7] CONFIRMED medium bug | packages/communications/src/Services/CommunicationManagerService.php:34 | "Idempotency race leaks" exists-then-acquire race; throw leaves lock for full TTL
- [R1:#8] CONFIRMED medium performance | packages/communications/src/Actions/PlanCommunicationDeliveriesAction.php:34 | "Unbounded plan transaction" per-item queries in txn; no cap; parse throws
- [R1:#9] CONFIRMED medium performance | packages/communications/src/Jobs/DispatchCommunicationDeliveriesJob.php:44 | "Unbounded dispatch get" all queued into memory; per-row transitions
- [R1:#10] CONFIRMED medium performance | packages/communications/src/Models/CommunicationDelivery.php:203 | "Unbounded cascade deletes" each()+chunk x6, no txn; children orphaned. DUP AUD:B3
- [R1:#11] CONFIRMED medium performance | packages/communications/src/Console/Commands/DispatchDueCommunicationsCommand.php:69 | "Dispatch count-plus-chunk" count then re-query; per-row get/saves/job storm
- [R1:#12] CONFIRMED medium security | packages/communications/src/Http/Controllers/WebhookController.php:25 | "Unbounded webhook payload" no size/depth cap before queue dispatch
- [R1:#13] CONFIRMED medium bug | packages/communications/src/Actions/RecordTrackingInteractionAction.php:20 | "Tokens never expire-checked" expired/revoked still tracked; free-form type
- [R1:#14] CONFIRMED medium bug | packages/communications/src/Actions/DispatchManagedNotificationAction.php:78 | "Unsafe notifiable handling" scalar TypeError; FQCN vs morph; hardcoded max 3
- [R1:#15] CONFIRMED medium security | packages/communications/src/Actions/ReceiveInboundCommunicationAction.php:86 | "Inbound stored raw" subject/body unredacted; sender morphs unvalidated
- [R1:#16] CONFIRMED low security | packages/communications/src/Http/Middleware/VerifyWebhookSignature.php:29 | "Provider enumeration codes" 404 vs 401; fixed algo/header only
- [R1:#17] CONFIRMED low bug | packages/communications/src/Jobs/ProcessWebhookEventJob.php:89 | "Fingerprint md5 collapse" json_encode false yields constant hash; wrong dedup
- [R1:#18] CONFIRMED low bug | packages/communications/src/Console/Commands/ExpireCommunicationsCommand.php:80 | "Expiry overwrites deadline" expires_at=now; skips events + deliveries
- [R1:#19] CONFIRMED low bug | packages/communications/src/Console/Commands/PruneCommunicationDataCommand.php:60 | "Before parse crashes" CarbonImmutable::parse uncaught on invalid --before
- [R1:#20] CONFIRMED low bug | packages/communications/src/Actions/ResolveCommunicationThreadAction.php:26 | "Thread check-then-create" race; non-unique (channel,external_thread_id)
- [R1:#21] CONFIRMED low bug | packages/communications/src/Actions/StartDeliveryAttemptAction.php:22 | "Attempt count race" read-modify-write sans lock; dup attempt_number
- [R1:#22] CONFIRMED low performance | packages/communications/src/Actions/RecalculateCommunicationStatusAction.php:21 | "Unbounded status prune" get-all statuses; count-then-delete single stmt
- [R1:#23] CONFIRMED low bug | packages/communications/src/Services/NotificationInboxService.php:192 | "Swallowed guard exception" InvalidArgumentException eaten; check skipped
- [R1:#24] CONFIRMED low bug | packages/communications/src/Actions/RecordProviderEventAction.php:81 | "Exists-then-insert race" unique violation surfaces raw QueryException
- [R1:#25] CONFIRMED low correctness | packages/communications/src/Policies/CommunicationPolicy.php:11 | "Policy methods missing" no update/delete (deny default); Filament risk unconfirmed
- [R1:#26] CONFIRMED low correctness | packages/communications/src/Listeners/AutoCaptureNotificationListener.php:61 | "Object-id state keys" spl_object_id reuse on GC; registry unpruned
- [AUD:B1] ADOPTED high bug | packages/communications/src/Actions/CreateTrackingTokenAction.php:26 | DUP R1:#1; merged, counted once
- [AUD:B2] ADOPTED high bug | packages/communications/src/Actions/AddCommunicationRecipientAction.php:25 | findOrFail trusts global scope; add OwnerWriteGuard
- [AUD:B3] ADOPTED medium bug | packages/communications/src/Models/Communication.php:221 | DUP R1:#10; merged, counted once
- [AUD:B4] ADOPTED high security | packages/communications/src/Actions/AddCommunicationRecipientAction.php:25 | DUP AUD:B2; merged, counted once
- [AUD:B5] ADOPTED medium security | packages/communications/src/Models/CommunicationAttachment.php:47 | disk/path/mime fillable; confirm server-side mimes/max
- [AUD:B6] ADOPTED low security | packages/communications/src/Support/DestinationProtectorService.php:12 | DUP R1:#3; merged, counted once
- [AUD:B7] ADOPTED info note | packages/communications/src/Http/Middleware/VerifyWebhookSignature.php:29 | GOOD: HMAC+hash_equals+300s ingress; no action
- [AUD:Q#16] ADOPTED high bug | packages/communications/src/Actions/CreateTrackingTokenAction.php:26 | DUP AUD:B1; queue row merged, counted once
- [AUD:Q#17] ADOPTED high bug | packages/communications/src/Actions/AddCommunicationRecipientAction.php:25 | DUP AUD:B2; queue row merged, counted once

---

## contacting

### E2E findings (verbatim)

End-to-end review of packages/contacting (models, actions, support, concerns, data, enums, config, migrations, factories, docs, tests). Full suite: 356 passed. 6 throwaway probes executed to confirm behaviors, then deleted.

FINDINGS

1) MEDIUM / bug — src/Actions/UpdateContactMethodAction.php:38-41 (same shape in UpdateSocialProfileAction.php:39-42): update silently clears primary/verified flags and wipes metadata.
Description: Update actions assign non-nullable DTO defaults unconditionally: is_primary/is_verified default false and metadata defaults [], so any update that does not restate them demotes a primary, un-verifies, and erases metadata. This contradicts the partial-update signals in the same methods (label kept when Optional, is_public via ?? keep). Probed: primary email with metadata ['k'=>'v'] updated with only type/purpose/value came back is_primary=false, metadata=[].
Evidence: `$contactMethod->is_primary = $data->isPrimary; ... $contactMethod->metadata = $data->metadata;` with `ContactMethodData(bool $isPrimary = false, bool $isVerified = false, array $metadata = [])`.
Recommendation: Make update tri-state (nullable/Optional flags; keep existing when absent) or document full-replace and require callers to restate flags.
Confidence: high (probed for contact methods; social profiles share the code shape, not separately probed).

2) MEDIUM / bug — src/Data/ContactMethodData.php + src/Actions/CreateContactMethodAction.php + src/Support/NormalizesEmailAddress.php: no validation; empty and invalid rows persist and are served.
Description: DTOs carry no rules and actions never validate. Probed: addContactMethod([]) persisted type='' value='' normalized=''; ContactMethodData::email('not-an-email') persisted value='not-an-email' with normalized_value=NULL, and resolveEmail() returned the raw invalid string (normalized ?? value fallback). Type/purpose allowlists are unenforced by default (strict_*=false) and purpose is never checked anywhere; over-long strings fail only at the DB with a 500.
Evidence: probe outputs above; `filter_var(...) ? $email : null` keeps raw value in `value` while nulling `normalized_value`.
Recommendation: Add Data rules (required type/value, max lengths, email format per type) or reject invalid in actions; make resolvers skip rows with null normalized_value for typed channels.
Confidence: high.

3) MEDIUM / bug+security — src/Concerns/HasContactMethods.php:19-22, src/Concerns/HasSocialProfiles.php:19-22: parent delete uses mass delete, bypassing child events/guards and orphaning cross-owner rows.
Description: `$model->contactMethods()->delete()` issues one DELETE under the current OwnerScope: child deleting/saving guards (incl. HasOwner global-write guard) never fire, observers/audit never run, and children owned by a different owner (possible on shared/global contactables, where the reference guard uses plain find) survive with a dangling polymorphic ref.
Evidence: `static::deleting(... $model->contactMethods()->delete());` vs HasOwner::bootHasOwner deleting guards.
Recommendation: Chunked `$model->contactMethods()->withoutOwnerScope()->each->delete()` (or explicit per-owner coverage) so guards/events run and no orphans remain; add a mixed-owner delete test.
Confidence: high on mechanism; medium on reachability (needs a global/shared parent with multi-owner children).

4) MEDIUM / bug — src/Actions/CreateContactMethodAction.php, UpdateContactMethodAction.php, CreateSocialProfileAction.php, UpdateSocialProfileAction.php vs src/Data/*.php: DTO fields silently ignored; validity/sort unsettable via actions.
Description: Callers can supply displayValue/normalizedValue/verifiedAt but no action reads them (displayValue is silently dropped and always overwritten by the normalizer). valid_from/valid_until/sort_order/verified_at exist on the models (fillable, used by resolvers/ordering) but are absent from the DTOs, so action-based callers cannot set validity windows or ordering and must bypass Actions.
Evidence: ContactMethodData has displayValue/normalizedValue/verifiedAt; actions reference only type/purpose/label/value/countryCode/flags/metadata.
Recommendation: Honor or remove the dead fields; add validity/sort fields to the DTOs or document direct-fill as the only path.
Confidence: high.

5) LOW / bug — src/Actions/CreateContactMethodAction.php:39, UpdateContactMethodAction.php:37: Optional-typed countryCode assigned without a guard.
Description: countryCode is typed `string|null|Optional` like label/handle/url, but unlike those it is assigned without `instanceof Optional` handling (`$data->countryCode ?? ...` passes an Optional object through), assigning an Optional object to a string(2) column when Data is built via partials → cast/PDO error.
Recommendation: Mirror the label guard: `$data->countryCode instanceof Optional ? <existing/null> : $data->countryCode`.
Confidence: medium (behavior certain; trigger requires partial/Optional construction).

6) LOW / security — src/Actions/BuildContactLinksAction.php:74-132, src/Support/SocialProfileConfig.php:26-36: link builders concatenate unvalidated input.
Description: mailto:/tel: built by raw concatenation (stored values are never validated, finding 2, so CRLF/quotes can persist); Telegram branch returns any dot-containing input raw; buildUrl concatenates the handle unencoded — probed: handle 'han dle/x?y=1' → 'https://www.facebook.com/han dle/x?y=1'. Impact needs unescaped rendering (Blade escapes by default) and a writer with contact access, so hardening-level.
Recommendation: rawurlencode handles/path segments, validate mailto/tel payloads, normalize telegram URLs through NormalizesUrl.
Confidence: high on behavior; low on impact.

7) LOW / bug — src/Support/SocialProfileConfig.php:62-73, src/Support/NormalizesUrl.php:22-28: handle extraction keeps query strings; uppercase schemes rejected.
Description: Probed: extractHandle('facebook','https://www.facebook.com/somehandle?ref=abc&x=1') → 'somehandle?ref=abc&x=1' (query pollutes the stored handle). NormalizesUrl's scheme check is case-sensitive, so 'HTTP://EXAMPLE.COM/Path' → null (probed), discarding a valid URL.
Recommendation: Strip query/fragment before segment extraction; compare schemes case-insensitively (and lowercase the host).
Confidence: high.

8) LOW / bug — models + actions: verification state is caller-asserted and inconsistent.
Description: is_verified/verified_at are fillable and settable via actions with no verification workflow, event, or authorization; setting is_verified=true leaves verified_at NULL (nothing ever syncs them; only factories set both). Consumers cannot distinguish real verification from self-assertion.
Recommendation: Sync verified_at when the flag flips, or add a dedicated Verify action + event and stop accepting the flags in create/update DTOs.
Confidence: high.

9) LOW / bug — database/migrations/2026_09_11_000003_add_primary_and_validity_indexes.php:178-241 vs Models/ContactMethod.php:254-286: primary unique index omits owner columns; MySQL expression risks collisions.
Description: App-level demotion partitions by (contactable, type, purpose, owner) but the DB backstop unique covers only (contactable, type, purpose): two owners holding primaries on one shared global contactable pass the app check then fail with a QueryException 500. Separately, the MySQL functional index uses CONCAT_WS('|',...) so tuples like ('a|b','c') vs ('a','b|c') collide into false violations, and CAST(CHAR(512)) can truncate long morph types into collisions.
Recommendation: Include owner columns in the partial unique (or forbid multi-owner primaries on global contactables explicitly); use JSON_ARRAY or length-prefixed concatenation for the MySQL expression.
Confidence: high on mechanism; medium-low on reachability (needs shared global contactables and/or '|' in free-form type/purpose).

10) LOW / performance — src/Actions/CreateContactSnapshotAction.php:81-98; BuildContactLinksAction.php:26; Concerns/HasContactMethods.php:130-153; snapshots migration; Models save path.
Description: fromBundle lazy-loads `owner` per source (getRelationValue query per item) plus one save + guard lookup each — no eager loading or bulk insert. forContactable()/resolveContacts() issue unbounded ->get(). contact_snapshots has no (source_type, source_id) index for lineage lookups. Primary saves run the DB-backed reference guard twice (save() pre-check + saving hook) plus a lockForUpdate parent read.
Recommendation: loadMissing('owner') + bulk insert for bundles; paginate/chunk link building; add the source index; skip the duplicate guard pass inside the transaction.
Confidence: high.

11) LOW / bug — src/Models/ContactSnapshot.php; src/Data/ContactSnapshotData.php; src/Contracts/*.php: snapshots mutable; dead DTO/contracts.
Description: Snapshot rows (meant as immutable history) are freely updatable/deletable with no append-only guard, and source_id/source_type are fillable so lineage is spoofable. ContactSnapshotData is constructed only in tests (the action takes models), and ContactMethodNormalizer/SocialProfileNormalizer are implemented by nothing (actions expose execute(), not normalize(), and are never bound to the contracts).
Recommendation: Block update/delete on snapshots (or document mutability); remove source_* from fillable; delete or wire up the dead DTO/contracts.
Confidence: high.

POSITIVES (brief)
- Owner scoping is real and tested: HasOwner on all 3 models, reference guard re-resolves via OwnerWriteGuard, CrossTenantIsolationTest green; owner columns excluded from fillable (tested).
- Repo rules honored: no FK constraints/cascades (tested), uuid PKs, no SoftDeletes, orchestration in Actions.
- Primary replacement is race-safe: parent lockForUpdate + in-transaction demotion + partial-unique backstop + non-destructive preflight, all covered by MigrationIndexesTest.
- Uniform AuthorizationException for owner-scoped misses (no existence oracle); raw migration SQL uses grammar wrapping + PDO quoting; no SQLi/SSRF/path-traversal/deserialization/file-IO surface; no cache use (no stampede) and no mutable static state (Octane-safe); PII channel-aware privacy defaults (email/phone/whatsapp/fax private by default).
- No routes/controllers/jobs/commands/widgets in this package, so no route-binding, pagination, or auth-surface findings apply here; output escaping of stored values/links remains the consumer's job.

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### contacting
Bugs:
- `Models/ContactMethod.php:56-74`, `SocialProfile.php:59-78` MEDIUM — `is_verified/verified_at/normalized_*` fillable, no verification guard in `saving`; `CreateContactMethodAction:42` sets `is_verified` with no visible authz.
- Null-parent `is_primary` orphans + global/owned primary split LOW — by-design, `syncSiblingPrimaryFlags` early-returns on null parent.
Security: clean — `saving` → `OwnerWriteGuard` on all 3 models; no `owner_*` in fillable.
Performance: clean — primary swap `lockForUpdate` + txn + partial unique.

### Verification verdicts

- [R1:#1] CONFIRMED medium bug | packages/contacting/src/Actions/UpdateContactMethodAction.php:38 | "Update clears flags" is_primary/verified/metadata overwritten by DTO defaults
- [R1:#2] CONFIRMED medium bug | packages/contacting/src/Data/ContactMethodData.php:12 | "No validation persists" empty/invalid rows saved; resolvers serve raw invalid
- [R1:#3] CONFIRMED medium bug | packages/contacting/src/Concerns/HasContactMethods.php:19 | "Mass delete orphans" ->delete() skips guards/events; cross-owner rows dangle
- [R1:#4] CONFIRMED medium bug | packages/contacting/src/Actions/CreateContactMethodAction.php:31 | "Dead DTO fields" display/normalized/verifiedAt ignored; validity/sort unsettable
- [R1:#5] CONFIRMED low bug | packages/contacting/src/Actions/CreateContactMethodAction.php:39 | "Optional countryCode leaks" Optional object passes ?? into string column
- [R1:#6] CONFIRMED low security | packages/contacting/src/Actions/BuildContactLinksAction.php:74 | "Raw link concatenation" mailto/tel/handle unencoded; hardening-level
- [R1:#7] CONFIRMED low bug | packages/contacting/src/Support/SocialProfileConfig.php:62 | "Handle keeps query" ?ref pollutes stored handle; uppercase scheme nulled
- [R1:#8] CONFIRMED medium bug | packages/contacting/src/Models/ContactMethod.php:68 | "Caller-asserted verification" no workflow/event; verified_at unsynced; adopt MEDIUM. DUP AUD:B1
- [R1:#9] CONFIRMED low bug | packages/contacting/database/migrations/2000_01_01_000001_create_contact_methods_table.php:62 | "Primary index omits" owner cols missing (path moved post-batch); CONCAT_WS collides
- [R1:#10] CONFIRMED low performance | packages/contacting/src/Actions/CreateContactSnapshotAction.php:81 | "Snapshot N+1 writes" per-item owner+save; unbounded gets; no source index; double guard
- [R1:#11] CONFIRMED low bug | packages/contacting/src/Models/ContactSnapshot.php:50 | "Mutable dead snapshots" update/delete open; lineage fillable; DTO/contracts unwired
- [AUD:B1] ADOPTED medium bug | packages/contacting/src/Models/ContactMethod.php:56 | DUP R1:#8; merged, counted once
- [AUD:B2] ADOPTED low bug | packages/contacting/src/Models/ContactMethod.php:260 | Null-parent primary early-return by design; orphans noted

---

## csuite

### E2E findings (verbatim)

End-to-end review: packages/csuite — metapackage, no runtime code.

STRUCTURE (verified): package contains only composer.json, CONTEXT.md, README.md, docs/*.md, tests/BundleTest.php. No src/, config/, routes/, database/migrations. Therefore the bulk of the checklist is N/A by design: no HasOwner queries/counts/route bindings/jobs/commands/widgets, no mass assignment, validation, auth, injection/XSS/SSRF/traversal/deserialization surfaces, no queries (N+1/indexes/pagination), no cache, no static state (Octane). Owner-scoping/security correctly documented as inherited from underlying packages (docs/01-overview.md:45-48).

FINDINGS:

1. [medium | bug] packages/csuite/tests/BundleTest.php:1 — Bundle test is orphaned, never executes. phpunit.xml testsuite points only at `tests/src`; tests/Pest.php `in(...)` list has no Csuite entry; nothing references BundleTest. Evidence: `<directory>tests/src</directory>` in phpunit.xml; Pest extends list lacks Csuite. So provider-rename / missing-package / constraint drift is undetected. Recommendation: move/copy test to tests/src/Csuite/ (or add testsuite dir + Pest mapping) and assert it runs in CI. Confidence: high.

2. [medium | bug] packages/csuite/README.md:152 — Broken license link `[LICENSE](LICENSE)`; verified `packages/csuite/LICENSE` does not exist (only repo-root LICENSE). Recommendation: link `../../LICENSE` or drop link. Confidence: high.

3. [low | bug] packages/csuite/composer.json:1 — Bundle includes `cashier` + `cashier-chip` but omits `filament-cashier` + `filament-cashier-chip`, which exist in-repo (FilamentCashierPlugin, FilamentCashierChipPlugin classes verified) and have tests (tests/src/FilamentCashier*). BundleTest plugin list (BundleTest.php:50-58) omits them too. Either an omission (no admin UI for bundled payment orchestration) or intentional — but unlike signals/growth/membership/moderation/references it is not listed in the documented exclusion policy (docs/01-overview.md:15-20, README.md:64-66). Recommendation: add the two plugins or document the exclusion. Confidence: med.

4. [low | bug] packages/csuite/docs/03-configuration.md:63-83 — Cart config snippet is stale vs actual packages/cart/config/cart.php. Doc shows `tables: alert_rules/alert_logs/daily_metrics/recovery_*` + `table_prefix: cart_`; actual config has `tables: snapshots/snapshot_items/snapshot_conditions`, no `table_prefix`, plus money.rounding_mode/owner/limits keys. Copy-paste yields unknown keys / missing behavior. Recommendation: regenerate snippet from real config. Confidence: high.

5. [low | bug] packages/csuite/docs/03-configuration.md:170-179 — Navigation example references filament-products/filament-orders/filament-shipping and AttributeResource, none of which are bundled. Misleading copy-paste in bundle docs. Recommendation: use bundled packages (filament-cart/vouchers/docs/...) in the example. Confidence: high.

6. [low | bug] packages/csuite/docs/04-usage.md:268-272 + docs/02-installation.md:20-28 — "Everything" / "Full Suite Installation" headings contradict the deliberate-subset bundle policy; `composer require aiarmada/commerce` does not install everything. Recommendation: rename to "Curated bundle". Confidence: med.

7. [low | security] packages/csuite/composer.json — `"minimum-stability": "dev"` on a published metapackage permits pre-release transitive deps for consumers. Common in monorepos using `self.version`, but worth a note. Recommendation: keep only if required for dev-branch installs; otherwise stable. Confidence: low (policy call).

8. [low | bug] packages/csuite/tests/BundleTest.php:33-34 — `file_get_contents` on derived package path with no existence check: a missing/renamed package dir yields PHP warning + TypeError instead of a clean assertion failure. Recommendation: assert `is_file()` first. Confidence: high.

POSITIVES (brief): correct `"type": "metapackage"`; all 17 aiarmada deps consistently pinned `self.version`; every bundled name verified present in packages/ with laravel provider extras; CONTEXT.md guardrails explicitly forbid adding runtime code here; docs honestly state no auto-registration of Filament plugins and per-package setup still required (99-troubleshooting); `commerce:setup` referenced in docs verified to exist (commerce-support SetupCommand, uses password prompts for secrets); BundleTest asserts both provider registration and Filament Plugin contracts — good coverage if wired into the suite (see finding 1).

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### csuite — metapackage, no `src/`/routes. No findings. Inherits bundled packages' findings.

---

### Verification verdicts

- [R1:#1] CONFIRMED medium bug | packages/csuite/tests/BundleTest.php:1 | "Orphaned bundle test" phpunit testsuite=tests/src only; Pest in() lacks Csuite; test never runs.
- [R1:#2] CONFIRMED low bug | packages/csuite/README.md:152 | "Broken license link" links LICENSE but packages/csuite/LICENSE does not exist; only repo-root LICENSE.
- [R1:#3] CONFIRMED low bug | packages/csuite/composer.json:11 | "Missing cashier plugins" filament-cashier(+chip) exist w/ plugins yet absent from require and exclusion policy.
- [R1:#4] CONFIRMED low bug | packages/csuite/docs/03-configuration.md:63 | "Stale cart snippet" tables/prefix keys mismatch cart.php snapshots/rounding_mode/owner/limits keys.
- [R1:#5] CONFIRMED low bug | packages/csuite/docs/03-configuration.md:170 | "Wrong nav example" cites filament-products/orders/shipping + AttributeResource; none bundled.
- [R1:#6] CONFIRMED low bug | packages/csuite/docs/04-usage.md:268 | "Everything heading misleading" Everything/Full Suite Installation headings vs curated-subset policy.
- [R1:#7] CONFIRMED low security | packages/csuite/composer.json:34 | "Dev stability published" minimum-stability dev on metapackage; prefer-stable set; policy call.
- [R1:#8] CONFIRMED low bug | packages/csuite/tests/BundleTest.php:33 | "Unchecked manifest read" file_get_contents on derived path w/o is_file; warning+TypeError not clean failure.

---

## customers

### E2E findings (verbatim)

E2E review: packages/customers (Laravel, PHP 8.4, Filament v5). 14 findings + positives. No XSS/SSRF/path-traversal/deserialization vectors found (no unserialize, no HTTP fetch, no file paths from input; all whereRaw use bindings). No Octane-unsafe static state (only config-key strings), no cache usage (no stampede), no unbounded pagination endpoints (no routes in package).

F1 — HIGH / security+bug — packages/customers/src/Services/CustomerResolver.php:34-72,106-116 — resolveExisting/resolve trust caller-supplied $sessionCustomer without owner validation. Both methods return/update/merge $sessionCustomer directly (line 53,60,64; line 107 `$sessionCustomer->update(['user_id'...])`) without checking it belongs to the resolved OwnerContext. A session-held customer from another owner gets claimed, profile-updated, address-synced, or merged. Evidence: `if ($sessionCustomer !== null) { return $sessionCustomer; }` with no belongsToOwner/forOwner check. Recommendation: re-fetch via OwnerWriteGuard::findOrFailForOwner(Customer::class, $sessionCustomer->id) (same pattern as LinkCustomerToPerson:32) or verify customersShareOwnerContext against resolved owner; return null on mismatch. Confidence: high.

F2 — HIGH / security — packages/customers/src/Actions/LinkCustomerToPerson.php:15-21 — executeByKey resolves arbitrary person id with no owner check. `$personClass::query()->whereKey($personId)->firstOrFail()` never constrains person to the customer's owner, so any owner can link its customer to another owner's Person (cross-tenant PII linkage + id-oracle via 404 vs success). Evidence snippet above; execute() guards only the Customer side (OwnerWriteGuard). Recommendation: scope person lookup to customer owner (forOwner) and reject mismatched owner tuples explicitly. Confidence: high.

F3 — HIGH / bug — packages/customers/src/Concerns/ResolvesCustomerIdentity.php:14-51 — findUserCustomer bypasses owner scope via user relations. `$user->customer()` / `customerProfile()` `getResults()` ignore OwnerScope, so a user row shared across owners resolves a foreign-owner Customer, which resolve() then updates/merges. The user_id fallback (line 48) is scope-filtered, making behavior inconsistent. Recommendation: after relation lookup, verify the result belongs to OwnerContext::resolve() (or re-query `Customer::where user_id` under scope only). Confidence: high.

F4 — HIGH / bug — packages/customers/database/migrations/2000_05_01_000001_create_customers_table.php + packages/customers/src/Concerns/HasCustomerProfile.php:41-64 — user_id has no unique index and getOrCreateCustomerProfile is check-then-create with no lock. Concurrent calls create duplicate Customer rows per user; findUserCustomer()->first() is then nondeterministic. Evidence: migration defines `$table->foreignUuid('user_id')->nullable()` with no unique; trait does `$this->customerProfile` read then `Customer::create` in a separate transaction. Recommendation: add scoped unique index on user_id (partial where not null) and use firstOrCreate/locking or catch unique violation. Confidence: high.

F5 — MEDIUM / bug — packages/customers/src/Models/Customer.php:158-236 — addContactMethod email uniqueness is check-then-act (TOCTOU). assertContactEmailIsUnique() then CreateContactMethodAction::execute() races under concurrency; the DB unique index (2026_09_08_000002 migration) will throw a raw QueryException → 500 instead of ValidationException. Recommendation: wrap create in try/catch for unique-violation and rethrow as ValidationException('email taken'). Confidence: high.

F6 — MEDIUM / bug — packages/customers/src/Models/Segment.php:342-359 vs packages/customers/src/Services/SegmentationService.php:227-251 — query-path and in-memory condition semantics disagree, so membership flaps. applyConditions: null value → skip condition (continue), unknown field → `whereRaw('1 = 0')` (match nothing). evaluateCondition: null field/value → return true (match everything), unknown field → false. A segment with an empty/unknown condition matches all customers via evaluateCustomer but none via rebuildSegment. Recommendation: unify (treat unknown/empty as match-nothing in both, or validate conditions at save). Confidence: high.

F7 — MEDIUM / performance — packages/customers/src/Services/SegmentationService.php:195-217 — getSegmentStats loads every member model to compute 3 counts (`$segment->customers` then collection where). Evidence lines 197-209. Recommendation: DB aggregates (`customers()->where(status...)->count()` etc.). Same class of issue, unbounded `->get()`: Segment.php:159 getMatchingCustomers, RebuildAllSegments.php:28,57,102, SegmentationService.php:70, RebuildSegmentsCommand.php:110,159 — large segments OOM and rebuildSegment fires N CustomerSegmentChanged events. Recommendation: chunkById + cursor sync, batch event or single SegmentRebuilt event. Confidence: high.

F8 — MEDIUM / performance — packages/customers/src/Models/Customer.php:189-218 + ResolvesCustomerIdentity.php:72-79 — email lookups wrap the column in LOWER(TRIM(COALESCE(...))), defeating the B-tree/functional index and diverging from the migration's NULLIF-normalized expression. Customer::normalizeEmail already lowercases, so query `normalized_value = ? OR (normalized_value null AND value = ?)` sargably. Confidence: med.

F9 — MEDIUM / security — packages/customers/src/Models/Customer.php:87-105 — over-broad $fillable: user_id (account hijack via mass assignment), status/is_guest/accepts_marketing (privilege), all lifecycle timestamps plus created_at/updated_at (audit forgery), metadata (unbounded JSON). Recommendation: remove timestamps/user_id/metadata from fillable; set via Actions with forceFill. Confidence: high.

F10 — MEDIUM / security — packages/customers/src/Models/Customer.php:297-304 — media collection 'documents' has no acceptsMimeTypes/size limits (avatar is restricted). Arbitrary uploads (SVG → stored XSS when served). Recommendation: whitelist mime types + max file size on 'documents'. Confidence: med.

F11 — MEDIUM / bug+security — packages/customers/src/Actions/SetDefaultCustomerAddress.php:14-42 — attaches any persisted Address to any Customer with no owner check (cross-owner linkage). Recommendation: require $address->belongsToOwner(customer owner) or same-tuple check like AssignCustomerToSegment. Confidence: high. Related (same file pattern): MergeCustomers.php:197-199 moveNotes mass-updates customer_id without per-note owner verification; mergeGroups (185-195) drops pivot role/joined_at via id-only sync. Confidence: med.

F12 — MEDIUM / bug — packages/customers/src/Actions/UpdateCustomerProfile.php:73-79 + Concerns/SynchronizesCustomerAddresses.php:30-60 — every checkout resolve() appends a new phone ContactMethod (no phone dedupe, unlike email) and potentially a new Address row (exact-match dedupe only); repeat checkouts bloat contact_methods/addresses unboundedly. Recommendation: dedupe phones like emails; update-in-place or cap addresses per customer/type. Confidence: high.

F13 — LOW / bug — packages/customers/src/Models/Segment.php:298-332 + migration 2000_05_01_000003 — app-level slug uniqueness checks (owner_type,owner_id,slug) but DB unique is (owner_scope,slug); dual sources of truth can disagree (two owners sharing an owner_scope collide at DB; check-then-insert races → raw 500). Recommendation: align both on one tuple and convert violation to ValidationException. Confidence: med.

F14 — LOW / bug+security — packages/customers/src/Policies/CustomerPolicy.php:50-75 (same in all 5 policies) — viewAny/create check only `isAuthenticated`; delete() carries a stale "cannot delete customers with orders" comment but enforces nothing, and Customer::booted deleting() hard-deletes notes. Also Customer.php:127-131 defaults accepts_marketing=true with no consent timestamp (consent/GDPR smell). Recommendation: implement the orders guard (or remove comment), restrict create/viewAny by permission, default accepts_marketing=false. Confidence: med. Missing-index note (low/perf): no index on customers.user_id, customers.created_at (created_days_ago condition), or (owner,status); pivot customer-first indexes were added 2026_09_11 — good.

Positives: consistent owner-tuple equality guards in Segment::addCustomer/removeCustomer, Assign/RemoveCustomerToSegment, MergeCustomers (with OwnerContext::withOwner), CustomerGroup member ops; policies enforce belongsToOwner/isGlobal; RebuildSegmentsCommand refuses cross-owner rebuilds; per-owner email uniqueness backed by partial DB indexes (owned+global); transactions in Create/Update/Resolver/Merge; no DB FK constraints/cascades, uuid PKs, no SoftDeletes, orchestration in Actions — all per repo rules.

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### customers (`aiarmada/customers`)
Bugs:
- `Models/Customer.php:103-104` MEDIUM (was HIGH) — `created_at/updated_at` fillable (audit-noise, not takeover).
- `Models/Segment.php:278-286` MEDIUM — `deactivated_at` never cleared on re-activate.
- `Models/Customer.php:320-328` MEDIUM — `deleting` orphans `contactMethods/socialProfiles/media`.
- `Actions/CreateCustomer.php:67-69` MEDIUM — `LinkCustomerToPerson` runs after txn returns → person-less customer on link failure.
Security: clean — `LinkCustomerToPerson` persons-global by design; customer side guarded.
Performance:
- `Segment:146-175` HIGH — `getMatchingCustomers()->get()` + `rebuildCustomerList sync(pluck)` loads all matches; chunk/paginate.
- `RebuildAllSegments:32-117` MEDIUM — per-segment `sync` + `pluck` + per-ID `event()`; N+1 events.
- DONE (2026-09-13, §8 item 6) — Missing `(owner,status)` composite MEDIUM. Fixed: `(owner_type, owner_id, status)` / `(owner_type, owner_id, is_active)` folded into the customers/segments creates.

### Migration-batch rows (§8, code may already be fixed)
| 6 | customers `(owner, status)` composites (§2) | Folded into the customers/segments creates | `packages/customers/docs/99-troubleshooting.md` |

### Verification verdicts

- [R1:#1] CONFIRMED high security | packages/customers/src/Services/CustomerResolver.php:53 | "Unvalidated session customer" resolveExisting/resolve return/update/merge sessionCustomer w/o owner check.
- [R1:#2] FALSE high security | packages/customers/src/Actions/LinkCustomerToPerson.php:18 | "Unscoped person link" matches FALSE-list: persons global by design; customer side guarded.
- [R1:#3] CONFIRMED high bug | packages/customers/src/Concerns/ResolvesCustomerIdentity.php:21 | "Relation bypasses scope" customer()/customerProfile() getResults ignore OwnerScope; no re-verify.
- [R1:#4] CONFIRMED high bug | packages/customers/database/migrations/2000_05_01_000001_create_customers_table.php:22 | "Duplicate user profiles" user_id nullable w/o unique; getOrCreate check-then-create w/o lock.
- [R1:#5] CONFIRMED medium bug | packages/customers/src/Models/Customer.php:171 | "Email TOCTOU race" assert-unique then create w/o catch; concurrent dup throws raw QueryException.
- [R1:#6] CONFIRMED medium bug | packages/customers/src/Models/Segment.php:348 | "Flapping segment membership" query path skips null/1=0 unknown vs in-memory true/false.
- [R1:#7] CONFIRMED high perf | packages/customers/src/Services/SegmentationService.php:197 | "Unbounded segment loads" DUP AUD:B5; stats/getMatching+sync unbounded; adopt HIGH.
- [R1:#8] CONFIRMED medium perf | packages/customers/src/Concerns/ResolvesCustomerIdentity.php:76 | "Non-sargable email lookup" LOWER(TRIM(COALESCE)) wraps column in 3 queries; NULLIF part unverified.
- [R1:#9] CONFIRMED medium security | packages/customers/src/Models/Customer.php:87 | "Over-broad customer fillable" user_id/status/timestamps/metadata fillable; hijack + audit forgery.
- [R1:#10] CONFIRMED medium security | packages/customers/src/Models/Customer.php:303 | "Unrestricted documents upload" documents collection w/o mime/size limits; avatar restricted.
- [R1:#11] CONFIRMED medium bug | packages/customers/src/Actions/SetDefaultCustomerAddress.php:30 | "Cross-owner address attach" attach w/o owner check; group pivot role/joined_at dropped.
- [R1:#12] CONFIRMED medium bug | packages/customers/src/Actions/UpdateCustomerProfile.php:73 | "Checkout contact bloat" phone added every resolve w/o dedupe; address exact-match dedupe only.
- [R1:#13] CONFIRMED low bug | packages/customers/src/Models/Segment.php:298 | "Dual slug truth" app checks owner tuple but DB unique is (owner_scope,slug); race 500s.
- [R1:#14] CONFIRMED low bug | packages/customers/src/Policies/CustomerPolicy.php:50 | "Permissive customer policies" viewAny/create auth-only; orders guard unenforced; marketing default true.
- [AUD:B1] ADOPTED medium bug | packages/customers/src/Models/Customer.php:103 | DUP R1:#9; created_at/updated_at fillable (audit-noise, not takeover).
- [AUD:B2] ADOPTED medium bug | packages/customers/src/Models/Segment.php:283 | deactivated_at set on deactivate, never cleared on re-activate.
- [AUD:B3] ADOPTED medium bug | packages/customers/src/Models/Customer.php:322 | deleting orphans contactMethods/socialProfiles/media; only notes/addresses detached.
- [AUD:B4] ADOPTED medium bug | packages/customers/src/Actions/CreateCustomer.php:67 | LinkCustomerToPerson runs after txn returns; link failure leaves person-less customer.
- [AUD:B5] ADOPTED high perf | packages/customers/src/Models/Segment.php:146 | DUP R1:#7; getMatching ->get() + sync(pluck) loads all matches.
- [AUD:B6] ADOPTED medium perf | packages/customers/src/Actions/RebuildAllSegments.php:85 | DUP R1:#7; per-segment sync + pluck + per-ID events.
- [AUD:B7] FIXED medium perf | packages/customers/database/migrations/2000_05_01_000001_create_customers_table.php:59 | DONE S8-6; (owner,status)+(owner,is_active) composites present in creates.
- [AUD:Q#6] FIXED medium perf | packages/customers/database/migrations/2000_05_01_000002_create_customer_segments_table.php:47 | DUP AUD:B7; S8 row 6 folded into creates.

---

## docs

### E2E findings (verbatim)

Review of packages/docs (all bodies deep-read: CONTEXT, config, routes, 4 controllers, 6 services, 14 models, numbering, rendering, mail, job, migrations, 3 blades).

HIGH
1. [bug] database/migrations/2000_06_01_000002_create_docs_tables.php — `doc_number` is globally unique (`$table->string('doc_number')->unique(...)`) while SequenceManager generates per-owner sequences with identical formats. With `docs.owner.enabled=true`, two owners' first invoices collide (e.g. two `INV-...-000001`) → unique-constraint 500. Fix: scope uniqueness to (owner_type, owner_id, doc_number) or make sequences globally unique.
2. [security] src/Models/Doc.php:87-114 + src/Services/DocService.php:208-246 + src/Http/Controllers/DocDownloadController.php:62-75 — `pdf_path` is fillable and `update(Doc,$data)` mass-assigns it (`$doc->update($data)`), while DownloadController/downloadPdf serve `$storage->download($doc->pdf_path)`. A caller able to update a doc can point `pdf_path` at another file on the disk and download it (stored arbitrary-file-read within disk root). Fix: remove `pdf_path` from fillable; set it only inside storePdf/generatePdf.

MEDIUM
3. [security] src/Services/DocService.php:444-453, src/Services/DocRenderService.php:455-465 — raw `doc_type` (free-form, user-persisted) interpolated into config keys: `config("docs.types.{$docType}.storage.disk")`. Dots in doc_type traverse the config array, influencing disk/path selection for PDF store/download. Fix: allowlist doc_type (DocType enum) before config lookup.
4. [security] src/Rendering/TiptapJsonRenderer.php:186-197 + src/Services/DocRenderService.php:57-83 — `isSafeUrl` permits any http(s) URL (incl. link-local/cloud-metadata IPs); these URLs are rendered into HTML that Browsershot fetches server-side during PDF generation → SSRF via attacker-controlled doc body image/src. Fix: block private/link-local IP ranges and restrict image hosts for PDF rendering.
5. [bug] src/Services/DocEmailService.php:257-283 — queued emails never leave `Queued`: only the sync branch sets `Sent`/`sent_at` (line 270); `Mail::queue()` path has no Sent transition/listener. Fix: update status via Mailable::afterCommit/SentMessage listener or queued-job callback.
6. [bug] src/Services/SequenceManager.php:30-41,61-93 + src/Models/DocSequence.php:88-114 — concurrent first-use races: no unique constraint on doc_sequences (doc_type+owner) so two txns create duplicate default sequences → duplicate doc numbers; and SequenceNumber get-or-create can double-insert on a new period → unique-violation 500. Fix: add unique index + catch unique violation with reselect/retry.
7. [bug] src/Services/DocService.php:47-68 + src/Numbering/Strategies/DefaultNumberStrategy.php:22-55 — `create()` numbers via uniqid-random strategy (non-sequential, contains dots, no uniqueness precheck → possible unique-500) while `createFromType()` uses atomic SequenceManager. Two inconsistent numbering paths. Fix: route both through SequenceManager.
8. [bug] src/Services/DocService.php:208-246 — `update()` mass-assigns caller `$data` (status, totals, doc_number, doc_type, pdf_path) bypassing the state machine, totals-vs-items consistency, and any owner re-check on $doc. Fix: explicit allowlist + force status changes through transitionStatusTo + recalc/verify totals.
9. [bug] src/Services/DocService.php:152-201 (and 76-84) — `createFromType()` without items persists caller-supplied monetary fields unvalidated (negatives allowed); `create()` accepts caller subtotal/tax/total that may contradict the items sum (only non-negativity asserted). Fix: always validate integer/non-negative and either recompute or reject mismatched totals.
10. [bug] src/Models/DocVersion.php:77-80 — `restore()` does `$this->doc->update($this->snapshot)` with stale totals/status/pdf_path, no template/totals validation, no new version snapshot, no owner guard. Fix: restore through DocService::update + record a version.
11. [bug] src/Services/DocPaymentRecorder.php:62 + src/Models/DocPayment.php:49-61 — paid-total sums ALL payments unfiltered by status while `status` is fillable, so a refunded/void payment still counts toward Paid; `payment_method` not validated against config; `paid_at`/`refunded_at` caller-controlled. Fix: filter `status=paid` (or equivalent), validate method, server-set timestamps.
12. [bug] src/States/DocStatus.php:108-129 — unknown status strings silently resolve to `Draft::class` instead of throwing, so typos/invalid input in create()/fromString() become Draft. Fix: throw InvalidArgumentException on unresolvable status.
13. [performance] src/Services/DueDocReminders.php:28-59 + src/Jobs/SendDocReminderJob.php:113-177 — `dueSoon()/overdue()` use unbounded `->get()` with `whereDoesntHave` + `whereJsonContainsKey` (unindexed JSON scan); job then loops per-doc with template query + email create + queue each (N+1, plus PDF regen per mail). Fix: chunk/limit, composite (status,due_date) index, eager-load.
14. [performance] src/Mail/DocMail.php:72-101 — `attachments()` regenerates the PDF via Browsershot on every send (`generatePdf(save:true)` unconditionally). Fix: reuse existing `pdf_path` when fresh (downloadPdf semantics) instead of re-rendering.

LOW
15. [security] src/Mail/DocMail.php:121-136 — CC taken from unvalidated `metadata['cc']` string: invalid value breaks queue workers; if metadata is caller-influenced it exfiltrates the PDF to an arbitrary address. Fix: validate email format, restrict/allowlist CC source.
16. [security] src/Services/DocEmailService.php:31-88 — recipient email/name never validated (send/sendReminder/job path from `customer_data['email']`). Fix: validate/normalize before creating the email record.
17. [security] routes/docs.php:9-17 + src/Services/DocEmailService.php:288-303 — public share/track routes have no `throttle` middleware; tracking tokens never expire. Fix: add throttle + token TTL.
18. [bug] src/Models/Doc.php:281-292 — deleting a Doc cascades DB rows but leaves the stored PDF orphaned. Fix: delete `pdf_path` from disk on delete.
19. [bug] src/Services/DocService.php:327-344 — `createVersion()` uses `max()+1` without a lock → duplicate `version_number` unique violation under concurrent updates. Fix: lock or retry-on-conflict.
20. [bug] src/Http/Controllers/DocPreviewController.php:18-43 — bound `Doc` instances skip the owner re-check that DocDownloadController performs; correctness depends entirely on the filament-docs route binding. Fix: mirror Download's OwnerWriteGuard check.
21. [performance] src/Rendering/TiptapJsonRenderer.php:31-73 — recursive render with no depth/node-count cap; unbounded `body` JSON → stack/memory DoS. Fix: cap depth and node count/size.
22. [performance] src/Services/SequenceManager.php:46-56,98-113 — read-only `preview()` inherits `lockForUpdate()` outside a transaction (useless lock attempt). Fix: separate locked/unlocked lookup.
23. [bug] src/DataObjects/DocData.php:51-94, src/Services/DocService.php:63-66 — `doc_type` persisted as free-form string with no DocType-enum validation in create/createFromType/update. Fix: validate against DocType.
24. [bug] src/Services/DocService.php:63-145 — `create()` has no DB transaction and creates no initial version, unlike `createFromType()`; post-save `generatePdf` failure leaves a half-initialized doc. Fix: wrap in transaction + create initial version.

POSITIVES (brief)
- Share links: 48-char random token, sha256-hashed storage, action allowlist, expiry/revocation checks, generic 404s, owner re-scoping, `no-store/nosniff/noindex` headers.
- Tracking click redirect sanitized (relative or http(s) only, fallback to app.url); filenames/PDF paths sanitized against traversal; rich-content dir rejects `..` and enforces owner prefix; Tiptap output escaped, `javascript:`/protocol-relative URLs blocked.
- Payment recorder uses SELECT FOR UPDATE, rejects overpayment and currency mismatch; minor-unit money enforced (major-unit keys rejected); DocMail PDF-failure degrades gracefully; no FK constraints/cascades, uuid PKs, HasOwner+OwnerScope used consistently, no SoftDeletes, no static Octane-unsafe state (registry is container-scoped), no unbounded pagination endpoints (only job queries, flagged above).

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### docs
Bugs:
- `DefaultNumberStrategy:52` MEDIUM — `uniqid('',true)` time-based predictable numbers.
- `DocDownloadController:62-64` MEDIUM — sync Browsershot/Chromium in GET; enqueue + 202.
- `DocService:86-89` LOW — caller `pdfOptions` merge; mitigated (`DocRenderService:177-210` constrained keys).
Security: verified safe — share `random(48)`+SHA256, hash lookup + re-scope, uniform 404, `no-store/noindex`. `TiptapJsonRenderer:186-197` blocks `javascript:`/traversal. Residual LOW: allows `http://` embeds.
Performance: `SequenceManager`/`DocPaymentRecorder` locks GOOD; `loadMissing(template,docable)` GOOD. Sync Browsershot in GET is the perf cost (see bugs).

### Migration-batch rows (§8, code may already be fixed)
| 11 | tax zone matching (§2) | No migration by design: request-scoped owner-aware resolver cache + `scoped()` bindings | `packages/tax/docs/99-troubleshooting.md` |

### Verification verdicts

- [R1:#1] CONFIRMED high bug | packages/docs/database/migrations/2000_06_01_000002_create_docs_tables.php:45 | "Global doc collision" doc_number globally unique vs per-owner sequences; owners collide.
- [R1:#2] CONFIRMED high security | packages/docs/src/Models/Doc.php:113 | "Fillable pdf hijack" pdf_path fillable + update() mass-assigns; download serves it; in-disk read.
- [R1:#3] CONFIRMED medium security | packages/docs/src/Services/DocService.php:446 | "Config key traversal" raw doc_type in docs.types.* keys (service+renderer); no allowlist.
- [R1:#4] DOWNGRADED (was medium) low security | packages/docs/src/Rendering/TiptapJsonRenderer.php:186 | "Permissive embed URLs" DUP AUD:B4; any http(s) allowed; adopt LOW.
- [R1:#5] CONFIRMED medium bug | packages/docs/src/Services/DocEmailService.php:266 | "Queued mail stuck" only sync branch sets Sent; Mail::queue path has no Sent transition.
- [R1:#6] CONFIRMED medium bug | packages/docs/src/Services/SequenceManager.php:32 | "Sequence first-use race" no unique on sequences(type+owner); period double-insert 500s.
- [R1:#7] CONFIRMED medium bug | packages/docs/src/Services/DocService.php:68 | "Split numbering paths" DUP AUD:B1; create() uniqid-random vs createFromType atomic sequence.
- [R1:#8] CONFIRMED medium bug | packages/docs/src/Services/DocService.php:239 | "Update bypasses machine" mass-assigns status/totals/number/type/pdf; no machine/totals/owner check.
- [R1:#9] CONFIRMED medium bug | packages/docs/src/Services/DocService.php:159 | "Unvalidated caller totals" no-items path persists monetary fields as-is; create() only non-negative.
- [R1:#10] CONFIRMED medium bug | packages/docs/src/Models/DocVersion.php:79 | "Raw version restore" restore() updates snapshot directly; stale fields, no version, no guard.
- [R1:#11] CONFIRMED medium bug | packages/docs/src/Services/DocPaymentRecorder.php:62 | "Unfiltered paid total" sum ignores status; method unvalidated; paid_at caller-set.
- [R1:#12] CONFIRMED medium bug | packages/docs/src/States/DocStatus.php:128 | "Silent draft fallback" unknown status resolves to Draft instead of throwing.
- [R1:#13] CONFIRMED medium perf | packages/docs/src/Services/DueDocReminders.php:40 | "Unbounded reminder scan" unbounded get + whereDoesntHave + JSON scan; job loops queries.
- [R1:#14] CONFIRMED medium perf | packages/docs/src/Mail/DocMail.php:82 | "Per-send PDF regen" attachments() always generatePdf(save:true); never reuses fresh pdf.
- [R1:#15] CONFIRMED low security | packages/docs/src/Mail/DocMail.php:129 | "Unvalidated CC metadata" CC from metadata cc string; breaks workers or exfils PDF.
- [R1:#16] CONFIRMED low security | packages/docs/src/Services/DocEmailService.php:31 | "Unvalidated email recipient" send/sendReminder take recipient email w/o validation.
- [R1:#17] CONFIRMED low security | packages/docs/routes/docs.php:9 | "Unthrottled public routes" track/share lack throttle; Crypt tracking tokens never expire.
- [R1:#18] CONFIRMED low bug | packages/docs/src/Models/Doc.php:283 | "Orphaned PDF delete" deleting cascades DB rows but never deletes pdf_path from disk.
- [R1:#19] CONFIRMED low bug | packages/docs/src/Services/DocService.php:330 | "Version max race" createVersion max()+1 w/o lock; concurrent dup version 500s.
- [R1:#20] CONFIRMED low bug | packages/docs/src/Http/Controllers/DocPreviewController.php:40 | "Preview skips recheck" bound Doc skips owner re-check; only string ids re-checked.
- [R1:#21] CONFIRMED low perf | packages/docs/src/Rendering/TiptapJsonRenderer.php:31 | "Uncapped JSON render" recursive render w/o depth/node cap; big body DoS.
- [R1:#22] CONFIRMED low perf | packages/docs/src/Services/SequenceManager.php:101 | "Lockless preview lock" preview() inherits lockForUpdate outside a transaction.
- [R1:#23] CONFIRMED low bug | packages/docs/src/DataObjects/DocData.php:70 | "Free-form doc type" docType persisted w/o DocType-enum validation.
- [R1:#24] CONFIRMED low bug | packages/docs/src/Services/DocService.php:63 | "Half-built create path" create() has no txn + no initial version; pdf failure partial.
- [AUD:B1] ADOPTED medium bug | packages/docs/src/Numbering/Strategies/DefaultNumberStrategy.php:52 | DUP R1:#7; uniqid('',true) time-based predictable suffix.
- [AUD:B2] ADOPTED medium bug | packages/docs/src/Http/Controllers/DocDownloadController.php:62 | sync storePdf/Browsershot render inside GET on cache miss.
- [AUD:B3] ADOPTED low bug | packages/docs/src/Services/DocRenderService.php:177 | caller pdfOptions merged but constrained to fixed keys; mitigated.
- [AUD:B4] ADOPTED low security | packages/docs/src/Rendering/TiptapJsonRenderer.php:186 | DUP R1:#4; javascript/traversal blocked; residual http embeds.
- [AUD:Q#11] ADOPTED low perf | audits/.staging-verify/vpkg/audit-docs.md:11 | tax-scoped S8 row; no migration by design; no action in docs.

---

## engagement

### E2E findings (verbatim)

End-to-end review: packages/engagement (domain package, no routes/controllers/widgets/jobs; UI lives in paired filament-engagement)

FINDINGS

1) [critical][bug] packages/engagement/src/Services/DefaultEngagementManager.php:454 + DefaultShareUrlGenerator.php:15 — share_token stored on Share differs from token embedded in share_url. Manager does `'share_token' => $options['token'] ?? Str::random(16)` then calls generateShareUrl($subject,$options) which independently computes `$options['token'] ?? Str::random(16)`. With no explicit token, two different randoms are generated, so `?share=` in share_url never matches the stored share_token and any token-based share verification always fails. Fix: generate once in share() (`$token = $options['token'] ?? Str::random(32)`), pass `['token'=>$token]` to generator, store same value. Confidence: high.

2) [high][bug] packages/engagement/src/Console/Commands/ReconcileEngagementCountersCommand.php:66-92 — reconcileAll/reconcileSingle run EngagementCounter/Follow/... queries with no OwnerContext and no OwnerBatchRunner. OwnerScope::apply() throws NoCurrentOwnerException when no owner/explicit-global is set, so under default config (`engagement.owner.enabled=true`) this command always fails in console, unlike SendDueRemindersCommand/MatchSubscriptionsCommand which correctly wrap in OwnerContext::withOwner(null)+OwnerBatchRunner. Fix: mirror that pattern; also reconcile per-owner so counters (whose unique key includes owner_type/owner_id) are recomputed per tenant. Confidence: high.

3) [high][bug] packages/engagement/src/Services/DefaultReminderManager.php:38-55,96-109 — offset/anchor reminders can never fire. setReminder() stores offset_minutes/anchor_type/anchor_code with remind_at=NULL, but nothing ever resolves them: Remindable::reminderAnchorTime() is never called anywhere in engagement or filament-engagement src (verified by grep), and dueReminders()/SendDueRemindersCommand filter `remind_at <= now`, which excludes NULL. Fix: resolve remind_at at setReminder time via subject->reminderAnchorTime() minus offset, plus a backfill/resolver for existing rows, or reject offset-without-anchor explicitly. Confidence: high.

4) [high][bug] Race-condition duplicates: follow/bookmark/respond/react/subscribe/collection-item all do check-then-create inside a transaction with no DB unique constraint and no row locking (migrations create only plain/composite indexes, e.g. follows migration index on follower+followable+status, non-unique; BookmarkCollectionItem firstOrCreate on (collection,bookmark) with no unique index). Concurrent requests create duplicate active rows; counters then double-count. Fix: add unique indexes on active-identity tuples (partial unique where status='active' on pgsql; app-level advisory/upsert fallback for MySQL) and use firstOrCreate/updateOrCreate handling UniqueConstraintViolation. Confidence: high.

5) [high][security] packages/engagement/src/Services/DefaultReminderManager.php:59 + Listeners/DispatchReminderThroughCommunications.php:33-40 — notification_class is accepted unvalidated from $options (also $fillable on Reminder) and later instantiated via app($notificationClass). The is_a(Notification::class) check bounds it to Notification subclasses, but any Notification class in the app (with constructor side effects / queued jobs / mail to arbitrary routes) becomes instantiable and dispatchable to an arbitrary recipient by whoever can call setReminder or write the Reminder row (Filament form field exists). Fix: allowlist notification classes in config, validate at setReminder, remove notification_class from $fillable / Filament editable fields. Confidence: med.

6) [medium][bug] packages/engagement/src/Console/Commands/SendDueRemindersCommand.php:66-67 — event(new ReminderDue) then markSent() with no try/catch and no markFailed path. If the communications listener throws (bad recipient, bad notification class, dispatch failure), the exception aborts the whole chunkById loop: that reminder stays pending (will retry, ok) but all remaining due reminders are skipped and the failure is never recorded via markFailed(). Fix: per-reminder try/catch → markFailed($reminder,$reason) and continue. Confidence: high.

7) [medium][bug] packages/engagement/src/Services/DefaultEngagementManager.php:277-311 — respond() on an existing (incl. cancelled) row always fires ResponseChanged and stamps changed_at, even when re-responding after cancel (should be ResponseCreated semantics) or with the identical type (spurious changed event + previous_response_type metadata pointing at itself). Counter handler then recalcs the same key twice. Fix: if previous status was cancelled → ResponseCreated path; if type unchanged → touch responded_at only / return existing. Confidence: med.

8) [medium][bug] MySQL gap in counters uniqueness: migration 2000_06_01_000010 creates unique(subject+counter+owner_type+owner_id) where owner cols are nullable — MySQL treats NULLs as distinct, so duplicate global counters are possible; the partial-unique backfill migration only runs on pgsql/sqlite. Fix: document MySQL limitation or use sentinel/coalesced unique key. Confidence: high.

9) [medium][security] No authorization on share(): follow/bookmark/respond/react/subscribe/remind all consult EngagementPolicyResolver, but share() (DefaultEngagementManager.php:440-475) has no policy hook at all (contract EngagementPolicyResolver has no canShare). Any caller can create share rows/URLs for any subject. Fix: add canShare() to resolver + authorize() in share(). Confidence: high (design gap; exploitability depends on exposed callers).

10) [medium][performance] packages/engagement/src/Services/DefaultSubscriptionManager.php:190 — findMatchingSubscription() loads ALL of a subscriber's (type,subject) subscriptions via ->get() and compares criteria in PHP. Heavy subscribers pay unbounded load per subscribe/unsubscribe. Fix: bound the candidate set (paginate, cap with warning) and/or store a criteria hash column (indexed) for equality lookup. Confidence: high.

11) [medium][performance] EngagementCounter listeners do synchronous full recounts per event: every follow/bookmark/reaction/response write triggers COUNT(*) recalc queries inside the originating request transaction (DefaultEngagementCounterService onX handlers). Under write bursts this multiplies DB load and transaction hold time. Fix: increment/decrement counters atomically in the write path and keep recalculate() for async reconcile (queue the listener or debounce). Confidence: med.

12) [low][bug] SendDueRemindersCommand.php:55 — ->limit($remaining) before ->chunkById(100) is dead code (chunkById overrides limit); bounding actually relies on the $remaining checks, which are correct. Harmless but misleading; remove limit. Confidence: high.

13) [low][bug] ReconcileEngagementCountersCommand.php:68-71 — offset-based ->chunk(100) over a DISTINCT select can skip/duplicate rows if counters change mid-run and is deprecated-style usage. Fix: select id+subject cols and chunkById, or iterate distinct pairs via cursor with explicit ordering. Confidence: med.

14) [low][security] Mass-assignment surface: morph identity columns (follower_type/id, etc.), status, timestamps, notification_class, channel, destination, message, notes, criteria, metadata are all $fillable on every model; owner_type/id correctly excluded. Risk is contained only if no generic request->validated() create/update path touches these models — Filament resources must use explicit field lists and the manager Actions. Recommend audit of filament-engagement forms + consider $guarded for *_type/*_id/status on models. Confidence: med.

15) [low][bug/performance] DefaultEngagementStateResolver subscriptionsFor()/remindersFor() and EngagementEventEngagementManager::stateFor() return lazy cursor() iterables. OwnerScope resolves OwnerContext at execution time, so iterating outside the request context (queued job, cached array) can throw or leak cross-owner rows; also unbounded. Fix: return Collection (bounded, ->limit()) instead of cursor. Confidence: med.

16) [low][performance] Missing composite indexes for hot queries: reminders (status,remind_at) for due scans; subscriptions (status,subscribable_type,subscribable_id) for matching; reactions/responses (subject,type,status) for keyed recounts. Individual indexes exist; composites would cut the per-event recount cost. Confidence: med.

17) [low][bug] BookmarkCollection::booted deleting handler does $collection->items()->each(delete) — N+1 deletes and relies on model events; large collections are slow and a failing item delete leaves partial state with no transaction. Fix: wrap in transaction or chunked delete. Confidence: med.

18) [low][security/robustness] Unvalidated free-form inputs throughout: response_type/reaction_type/reminder_type/subscription_type/channel/notification_level/source/visibility/notes/message/destination/criteria/metadata have no length or charset validation; over-255 strings cause DB exceptions (500s); stored notes/message/metadata render downstream (Filament escapes by default — verify any ->html() usage in filament-engagement). DefaultShareUrlGenerator also concatenates an unencoded token onto a model-supplied base URL. Fix: validate lengths at manager boundaries, urlencode token. Confidence: med.

19) [low][security] MatchSubscriptionsCommand builds match context from $model->attributesToArray() (all columns incl. potentially sensitive) and broadcasts it via SubscriptionMatched events to all listeners. Fix: project only whitelisted attributes. Console-only trigger; low exposure. Confidence: med.

NOTES / NON-ISSUES
- Owner scoping done right: all 10 models use HasOwner+HasOwnerScopeConfig with engagement.owner key; write paths that take existing rows use OwnerWriteGuard::findOrFailForOwner; send/match commands use OwnerBatchRunner. No unscoped DB::table/route bindings/jobs/widgets in this package.
- Lifecycle discipline good: status transitions instead of deletes; no SoftDeletes, no FK cascades, uuid PKs, money N/A, no static/Octane-unsafe state (stateless services; OwnerContext is request-bag based), no cache stampede surface (no caching), no SSRF/path-traversal/deserialization sinks found (share URL is stored, never fetched).
- Tests: no package-level tests directory for engagement; only factories ship. Recommend Pest coverage for share-token round-trip, reconcile command under owner context, offset reminders, and concurrent follow dedup.

POSITIVES
- Clean contract/service split with guard rails (EngagementModelGuard), policy resolver seam, transactions around multi-write flows, afterCommit queued notification, idempotency key on reminder dispatch, per-type counter reconciliation covering stale keys.

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### engagement
Bugs:
- DONE (2026-09-13, §8 item 2) — follow/bookmark/react/respond HIGH — `first→create`, no lock, no composite unique (only counters unique) → twins skew counters. Fixed: actor+subject composites folded into creates; lock + 23000 rescue returns existing.
- DONE (2026-09-13, §8 item 2) — Reminder double-send HIGH — `markSent/Failed:111-127` no status precondition; `SendDueRemindersCommand:56-73` re-check non-atomic, no lease. Fixed: status preconditions + row locks + send lease.
- DONE (2026-09-13, §8 item 2) — `share_token Str::random(16)` indexed not unique MEDIUM → collisions. Fixed: unique folded into the shares create.
- Unbounded reminder/subscription creation MEDIUM — no dedup/throttle.
- `BookmarkCollectionItem` dedup unverified — `firstOrCreate` without confirmed unique; verify migration.
Security:
- Actor/subject owner-equality MEDIUM/HIGH-verify — `DefaultEngagementPolicyResolver` all `true`; creates rely on ambient context. Confirm resolver.
- State/counter reads rely on global scope LOW — prefer explicit `forOwner`.
Performance:
- Per-type counter fan-out MEDIUM — `recalculateResponses/Reactions:228-275` per-type `count` + `updateOrCreate`.
- GOOD: `aggregateCounters:141-177` single round-trip; `dueReminders cursor()`; counters unique.

### Prior-audit fix-first rows
| 30 | engagement | `Services/DefaultEngagementManager` follow/bookmark/react/respond | No lock + no unique → duplicates skew counters | HIGH |
| — | engagement | Reminders `markSent/Failed:111-127` | No status precondition, no lease → double-send | HIGH |

### Migration-batch rows (§8, code may already be fixed)
| 2 | engagement dup uniques + `share_token` + reminder lease (§3) | Actor+subject composites + `share_token` unique folded into creates; lock + rescue; status preconditions + send lease | `packages/engagement/docs/01-overview.md`, `99-troubleshooting.md` |

### Verification verdicts

- [R1:#1] CONFIRMED critical bug | packages/engagement/src/Services/DefaultEngagementManager.php:540 | "Mismatched share token" stored token vs URL token generated independently; never match.
- [R1:#2] CONFIRMED high bug | packages/engagement/src/Console/Commands/ReconcileEngagementCountersCommand.php:68 | "Ownerless reconcile command" no OwnerContext/BatchRunner; owner.enabled defaults true.
- [R1:#3] CONFIRMED high bug | packages/engagement/src/Services/DefaultReminderManager.php:40 | "Dead offset reminders" offset stored w/ remind_at NULL; anchor fn never called; excluded.
- [R1:#4] DOWNGRADED (was high) medium bug | packages/engagement/src/Services/DefaultEngagementManager.php:571 | "Race-condition duplicates" 4 flows FIXED (DUP AUD:B1); subscribe+collection-item still racy.
- [R1:#5] CONFIRMED high security | packages/engagement/src/Services/DefaultReminderManager.php:61 | "Unvalidated notification class" class from options/fillable via app(); only is_a bound.
- [R1:#6] CONFIRMED medium bug | packages/engagement/src/Console/Commands/SendDueRemindersCommand.php:96 | "Aborting reminder loop" event+markSent w/o try/catch; throw aborts chunk; no markFailed.
- [R1:#7] CONFIRMED medium bug | packages/engagement/src/Services/DefaultEngagementManager.php:342 | "Spurious response changed" existing/cancelled/same-type always fires ResponseChanged.
- [R1:#8] CONFIRMED medium bug | packages/engagement/database/migrations/2000_06_01_000009_create_engagement_counters_table.php:34 | "MySQL counter gap" unique has nullable owner cols; partial unique pgsql/sqlite only.
- [R1:#9] CONFIRMED medium security | packages/engagement/src/Services/DefaultEngagementManager.php:526 | "Unauthorized share creation" share() has no policy hook; no canShare; siblings authorize.
- [R1:#10] CONFIRMED medium perf | packages/engagement/src/Services/DefaultSubscriptionManager.php:190 | "Unbounded criteria scan" findMatching loads all via get() + PHP compare per call.
- [R1:#11] CONFIRMED medium perf | packages/engagement/src/Services/DefaultEngagementCounterService.php:343 | "Synchronous recount listeners" each write triggers COUNT recalc inside request txn.
- [R1:#12] CONFIRMED low bug | packages/engagement/src/Console/Commands/SendDueRemindersCommand.php:56 | "Dead limit call" limit() before chunkById overridden; bounding via remaining checks.
- [R1:#13] CONFIRMED low bug | packages/engagement/src/Console/Commands/ReconcileEngagementCountersCommand.php:71 | "Offset distinct chunk" chunk() over DISTINCT skips/dups on mid-run change.
- [R1:#14] CONFIRMED low security | packages/engagement/src/Models/Reminder.php:53 | "Fillable morph identities" morph cols/status/timestamps/notification_class fillable.
- [R1:#15] CONFIRMED low bug | packages/engagement/src/Services/DefaultEngagementStateResolver.php:79 | "Lazy cursor resolvers" subscriptions/reminders return unbounded cursor; stateFor is array.
- [R1:#16] CONFIRMED low perf | packages/engagement/database/migrations/2000_06_01_000008_create_engagement_reminders_table.php:27 | "Missing composite indexes" single indexes only; no hot-path composites.
- [R1:#17] CONFIRMED low bug | packages/engagement/src/Models/BookmarkCollection.php:66 | "N-plus-one collection delete" items()->each(delete) w/o txn; slow + partial on failure.
- [R1:#18] CONFIRMED low security | packages/engagement/src/Services/DefaultShareUrlGenerator.php:17 | "Unvalidated free-form inputs" no length checks at boundaries; token concatenated unencoded.
- [R1:#19] CONFIRMED low security | packages/engagement/src/Console/Commands/MatchSubscriptionsCommand.php:103 | "Broadcast sensitive attributes" match context from attributesToArray; console-only.
- [AUD:B1] FIXED high bug | packages/engagement/database/migrations/2000_06_01_000001_create_engagement_follows_table.php:39 | DUP R1:#4; DONE S8-2; actor+subject uniques + lock + 23000 rescue.
- [AUD:B2] FIXED high bug | packages/engagement/src/Services/DefaultReminderManager.php:113 | DONE S8-2; markSent/Failed pending-precondition + row locks; atomic claim.
- [AUD:B3] FIXED medium bug | packages/engagement/database/migrations/2000_06_01_000010_create_engagement_shares_table.php:42 | DONE S8-2; share_token unique folded into shares create.
- [AUD:B4] ADOPTED medium bug | packages/engagement/src/Services/DefaultReminderManager.php:31 | setReminder has no dedup/throttle; repeats create unbounded rows.
- [AUD:B5] ADOPTED medium bug | packages/engagement/src/Services/DefaultEngagementManager.php:571 | DUP R1:#4; firstOrCreate w/o unique; items migration indexes only.
- [AUD:B6] ADOPTED medium security | packages/engagement/src/Services/DefaultEngagementPolicyResolver.php:15 | resolver returns true; creates rely on ambient context.
- [AUD:B7] ADOPTED low bug | packages/engagement/src/Services/DefaultEngagementCounterService.php:115 | state/counter reads rely on global scope; prefer explicit forOwner.
- [AUD:B8] ADOPTED medium perf | packages/engagement/src/Services/DefaultEngagementCounterService.php:228 | per-type count + updateOrCreate fan-out in recalculate.
- [AUD:Q#30] FIXED high bug | packages/engagement/database/migrations/2000_06_01_000001_create_engagement_follows_table.php:39 | DUP AUD:B1; fix-first row 30 resolved by S8-2.
- [AUD:Q#FF2] FIXED high bug | packages/engagement/src/Services/DefaultReminderManager.php:113 | DUP AUD:B2; fix-first double-send row resolved by preconditions.
- [AUD:Q#2] FIXED high bug | packages/engagement/database/migrations/2000_06_01_000010_create_engagement_shares_table.php:42 | DUP AUD:B1; S8 row 2 composites+token+lease confirmed.

---

## events

### E2E findings (verbatim)

End-to-end review of packages/events (Laravel monorepo, PHP 8.4). Deep-read: CONTEXT.md, config/events.php, EventsServiceProvider, ScopesByEventOwner, EventSubmissionOwnerScope, EventWriteGuard, Event/EventRegistration/EventOccurrence/EventSession/Venue/Taxonomy/Submission/Attachment/Media/Link/Involvement/Participant/Item models, RegistrationService, RegisterForFree, PromoteInterested, RecordAgentTicketSale, RecordWalkIn, CreateRegistrationsFromOrder, CreateEventComponentRegistrations, Update/CreateSession, SyncEventClassifications, SyncVenueFacilities, SyncManagementAssignmentToAuthz, CloneEventContents, FinalizeOccurredEventOrders(+Command), DefaultEventCheckInService, DefaultEventLifecycleWorkflow, EventQueryService, EloquentEventSearchEngine, EventSearchDocumentBuilder, EventNotificationDispatcher, EventTaxonomyHierarchyService, EventPolicy, BuildEventSearchDocumentJob, observers, notifications, blades, all cited migrations.

FINDINGS

1. [high/bug] packages/events/src/Actions/SyncEventClassificationsAction.php:63 — delete-then-recreate classifications with no transaction, no owner guard, N+1 taxonomy lookup, per-value firstOrCreate race.
Description: handle() deletes all event-level classifications, then recreates them one row at a time with `EventTaxonomy::query()->find()` inside the loop and `EventTerm::firstOrCreate(['event_taxonomy_id','code' => Str::slug($name)])` per value. No DB transaction (readers observe an empty window; crash leaves data deleted), no EventWriteGuard call (relies on callers), and concurrent syncs can duplicate terms if no unique key exists on (taxonomy, code).
Evidence: `EventClassification::query()->where('event_id',...)->...->delete(); ... foreach ($termIds...) { $taxonomy = EventTaxonomy::query()->find(...); EventClassification::query()->create([...]); }`
Recommendation: Wrap in DB::transaction, call EventWriteGuard::findOrFail($event->id), eager-load taxonomies once (whereIn + keyBy), add unique index on event_terms(event_taxonomy_id, code).
Confidence: high.

2. [high/security] packages/events/src/Models/Venue.php:65, EventTaxonomy.php:27, EventTerm.php:33, EventRole.php:29, EventTermPolicy.php:24, FacilityType.php:29, VenueSpace.php:45, VenueSpaceType.php:32, VenueFacility.php:40 — 9 models with no owner boundary at all.
Description: `grep -L HasOwner|ScopesByEventOwner|EventSubmissionOwnerScope Models/*.php` returns exactly these files (+ EventSeriesItemPivot and EventSubmission, the latter covered by EventSubmissionOwnerScope). Any owner context can read/write shared Venue/Taxonomy/Term/Role/FacilityType rows; SyncEventClassificationsAction::firstOrCreateTaxonomy/firstOrCreate term and SyncVenueFacilitiesAction let one tenant mutate or poison names/codes visible to all tenants, and events can reference venues owned by nobody.
Evidence: `final class EventTaxonomy extends Model { use HasFactory; use HasUuids;` (no scope trait); same shape for the other 8.
Recommendation: Either document these as intentional global vocabularies with admin-only write paths enforced in filament-events/policies, or add HasOwner / ScopesByEventOwner as appropriate; at minimum add a policy + write guard on taxonomy/term/facility-type creation.
Confidence: high (for missing boundary); med that exploitability matters (depends on who can reach the sync actions — filament-events not reviewed).

3. [high/bug] packages/events/src/Actions/PromoteInterestedToConfirmedAction.php:30 and RecordAgentTicketSaleAction.php:55 — capacity check-then-act with no lock/transaction; agent sale ships inventory before registrations exist.
Description: Promote reads capacityRemaining() then transitionStatus() with no LockEventRegistrationScopeAction and no transaction, so concurrent promotes overbook (RegisterForFree and CreateRegistrationsFromOrder correctly take the lock). RecordAgentTicketSaleAction checks capacity only when `enforce_scope_capacity_on_paid_registrations` (default false), takes no lock, loops quantity with no transaction, and calls inventory->shipFromDefault() before any registration is created — a later failure orphans shipped inventory.
Evidence: `$capacityRemaining = $this->capacityRemaining($registration); if (... < 1) throw ...; $registration->transitionStatus(Confirmed::class);` ; `$this->enforceCapacity(...); $this->inventory->shipFromDefault(...); for (...) { $this->registrations->register(...); ... }`
Recommendation: Use LockEventRegistrationScopeAction + DB::transaction in both; move shipFromDefault inside the transaction after successful registration creation (or compensate on failure); consider defaulting paid capacity enforcement to true.
Confidence: high.

4. [medium/security] packages/events/src/Services/RegistrationService.php:100 — registration items/answers/participants accepted with almost no validation; prices, ticket types, statuses caller-controlled.
Description: register() does `$registration->items()->create(array_merge($itemData, $scopeFields))` and same for answers — ticket_type_id is never verified to belong to the event scope, quantity/unit_price/total_price/currency/status/metadata are taken verbatim (price tampering, cross-event ticket types). Participants strip only email/phone/company/answers, leaving participant_type/id (arbitrary morph), status, age, gender unvalidated. Email/phone get only trim (cleanString, ~line 343), no format validation.
Evidence: `foreach ($data['items'] as $itemData) { $registration->items()->create(array_merge($itemData, $scopeFields)); }`
Recommendation: Validate items against scope ticket types, recompute/verify prices server-side, allowlist participant morph types and status values, validate email format.
Confidence: high.

5. [medium/bug] packages/events/src/Actions/UpdateEventSessionAction.php:30 (+ UpdateEventOccurrenceAction.php:33) — status written via mass update, bypassing the spatie state machine.
Description: Actions intersect caller attributes with getFillable() (which includes `status`) and call `$session->update($allowed)`, setting timestamps via match(). Direct attribute assignment does not run transitionTo() allowed-transition validation, so illegal jumps (e.g. completed → draft) succeed silently and lifecycle events/change-chain entries for most transitions are skipped (only a narrow status-change branch fires DispatchEventChangeChainAction). The lifecycle workflow (DefaultEventLifecycleWorkflow) correctly uses transitionTo().
Evidence: `$fillable = $session->getFillable(); $allowed = array_intersect_key(...); ... $session->update($allowed);`
Recommendation: Remove `status` from the mass-update allowlist and route status changes through EventLifecycleWorkflow (or call transitionTo() explicitly and reject unknown transitions).
Confidence: med (relies on spatie/laravel-model-states only validating in transitionTo, which is its documented behavior).

6. [medium/bug] packages/events/src/Resolvers/DefaultEventRegistrationEligibility.php:18 — eligibility checks occurrence status only; cancelled/completed sessions or events still accept registrations.
Description: ensureEligible() returns early when occurrence is null or canAcceptRegistrations(); it never inspects session status or event status. A cancelled session under a published occurrence, or an event-level registration on a cancelled event, passes eligibility.
Evidence: `if ($scope->occurrence === null || $this->lifecyclePolicy->canAcceptRegistrations($scope->occurrence)) { return; }`
Recommendation: Also evaluate session status (when scope has a session) and event status via LifecyclePolicy.
Confidence: high.

7. [medium/security] packages/events/src/Services/DefaultEventCheckInService.php:95 — attendee_type/attendee_id arbitrary morph + unvalidated metadata/notes/verified_by.
Description: checkInWithResult() persists attendee_type/attendee_id straight from $data into EventAttendance with no allowlist, letting callers attach check-ins to arbitrary model types. notes/metadata/check_in_source/verified_by_user_id/performed_by_* are likewise unvalidated.
Evidence: `'attendee_type' => $data['attendee_type'] ?? null, 'attendee_id' => $data['attendee_id'] ?? null, ... 'metadata' => $data['metadata'] ?? null,`
Recommendation: Allowlist attendee types (or require registration/participant/pass identity), validate source/verified_by, cap notes/metadata size.
Confidence: high.

8. [medium/performance] packages/events/src/Actions/RegisterForFreeAction.php:160 — idempotency check loads entire scope then filters metadata in PHP.
Description: findIdempotentRegistrations() runs `$query->get()->filter(fn => data_get(metadata,'registration.idempotency_key') === $key)` — every free registration scans all rows in the event/occurrence/session scope with no usable index. Grows linearly with event size on the hottest path.
Evidence: `$existing = $query->get()->filter(static fn (EventRegistration $r): bool => data_get($r->metadata ?? [], 'registration.idempotency_key') === $idempotencyKey)`
Recommendation: Store idempotency_key in a real indexed column (or JSON->> expression index where supported) and filter in SQL with limit.
Confidence: high.

9. [medium/performance] Unbounded result sets: EventQueryService.php:21 findPublished()->get(), :48 findByOwner()->get(); EloquentEventSearchEngine.php:~80 limit cast with no max/default; EventNotificationDispatcher.php:45 fallback registrations ->get(); FinalizeOccurredEventOrdersAction.php:23 ->get() + per-row fulfill; Console/Commands/FinalizeEventOrdersCommand.php:30 ->get().
Description: Five read paths materialize unbounded collections; search `limit` accepts any client-supplied int (including huge) and defaults to unlimited. Large tenants risk memory exhaustion and mail-storm loops (dispatcher notifies synchronously per recipient).
Evidence: `return $eventClass::published()->get();`, `$query->limit((int) $criteria['limit']); return $query->get();`
Recommendation: Paginate/chunk all five; clamp search limit (e.g. max 100, default 25); queue change-notice fan-out.
Confidence: high.

10. [medium/bug] packages/events/src/Console/Commands/FinalizeEventOrdersCommand.php:24 — command runs owner-scoped queries with no owner context.
Description: handle() queries EventOccurrence directly, but ScopesByEventOwner's global scope calls OwnerContext::assertResolvedOrExplicitGlobal(), which throws when console has neither an owner nor an explicit global context. The command will fail unless the operator already set one up; there is no --owner/--global option.
Evidence: `$query = EventOccurrence::query()->where('status', ...COMPLETED);` with no OwnerContext setup.
Recommendation: Add --owner-type/--owner-id and --global options and wrap execution in OwnerContext::withOwner()/explicit-global.
Confidence: med (depends on commerce-support OwnerContext console behavior; the assert call is confirmed in ScopesByEventOwner.php:36).

11. [medium/security] packages/events/src/Policies/EventPolicy.php:15 + EventsServiceProvider.php:~140 — only Event has a policy; viewAny always true; 60+ other models unregistered.
Description: Gate::policy is registered solely for the Event class. viewAny() returns true unconditionally and view() trusts isPubliclyVisible(). Whether registrations/occurrences/venues are authorization-checked depends entirely on filament-events (out of scope) — the domain package provides no defense in depth.
Evidence: `public function viewAny(mixed $user): bool { return true; }`, `Gate::policy($eventClass, EventPolicy::class);` (sole registration).
Recommendation: Add policies (or explicit deny-defaults) for registration/occurrence/session/venue/submission, or document that filament-events owns all authorization and verify it there.
Confidence: med.

12. [medium/bug] packages/events/src/Actions/CloneEventContentsAction.php:60 — clone has no write guard, no transaction, per-row saves; unknown relation names silently skipped.
Description: handle() takes raw source/target IDs with no EventWriteGuard verification, saves each replica individually (N+1 writes, partial clone on failure), and `continue`s on unknown $relations entries so caller typos silently no-op. (Cross-owner target writes are mitigated by the ScopesByEventOwner saving guard, which re-checks event visibility — hence medium, not high.)
Evidence: `$modelClass = self::MODEL_MAP[$relation] ?? null; if ($modelClass === null) { continue; } ... $replica->save();`
Recommendation: Verify both events via EventWriteGuard, wrap in DB::transaction, throw on unknown relation names, consider insert batching.
Confidence: high.

13. [low/security] packages/events/src/Models/Event.php:162 (also EventSeries.php:43, EventItinerary.php:40, EventTemplate.php:54, EventOrganizer.php:58) — owner_type/owner_id in $fillable.
Description: Direct-owner models mass-assign the owner tuple, so any create/update path passing user input to fill()/create() can set or reassign ownership. EventWalkIn/EventHeadcountLog (also HasOwner) correctly exclude owner columns from fillable — inconsistent. Whether HasOwner auto-assign overwrites on create was not verified in commerce-support.
Evidence: `protected $fillable = ['owner_type', 'owner_id', 'created_by_type', ...]`
Recommendation: Remove owner_* from fillable (rely on OwnerContext auto-assign) or explicitly guard reassignment on update.
Confidence: med.

14. [low/bug] packages/events/src/Models/EventRegistration.php:98 — fillable includes registrant morph, parent_registration_id, is_bundle_root, pass_entitlements; createFromOrderItem defaults currency to 'USD' (RegistrationService.php:~310) vs config default MYR.
Description: Bundle linkage fields are caller-settable with no same-event verification of parent_registration_id; registrant_type/id unverified. Currency default inconsistency ('USD' hardcoded vs events.defaults.currency MYR).
Evidence: `'registrant_type','registrant_id',...,'parent_registration_id','is_bundle_root','pass_entitlements',` ; `'currency' => $orderItemData['currency'] ?? 'USD'`
Recommendation: Verify parent/registrant belong to the same event scope; use config('events.defaults.currency').
Confidence: med.

15. [low/bug] Interested status is not capacity-blocking + walk-in count clamp + slug non-unique.
Description: (a) CAPACITY_BLOCKING_STATUSES (EventRegistration.php:94) omits `interested`, so unlimited Interested RSVPs accumulate and later Promote calls contend for scarce seats. (b) RecordWalkInAction.php:37 uses `max(1, $count)`, silently turning 0/negative counts into 1 instead of validating. (c) events.slug is a nullable non-unique index (migration 000001:23) while EventQueryService::findBySlug() returns first() — duplicate slugs are ambiguous.
Recommendation: Decide whether Interested should block capacity (or cap it); throw on count < 1; scope-unique slugs or order+document first-match.
Confidence: med.

16. [low/performance] Missing composite index for capacity math; float price comparisons.
Description: capacityRemaining() (EventOccurrence.php:526, EventSession.php:467) filters (event_occurrence_id|event_session_id, status) + sum — only single-column indexes exist; add composite (scope_id, status) indexes. effectivePricingMode() and ObserveEventTicketTypePricingConsistency compare `(float) $price` — fine for zero checks on int minor units but float-cast of money is a smell; compare `(int) $price === 0`.
Evidence: `->whereIn('status', $blockingStatuses)->sum('total_participants')`; `$hasPaid = $ticketTypes->contains(fn ($t): bool => (float) $t->price > 0);` (Event.php:560, Occurrence:442, Session:422).
Confidence: med.

17. [low/bug] packages/events/src/Actions/SyncManagementAssignmentToAuthzAction.php:58 — silent no-op when role missing; raw pivot write.
Description: assignManagerToScope() returns silently if the authz role row is absent — the management assignment looks successful while the permission never materializes. The DB::table($pivotTable)->updateOrInsert() bypasses model scopes (acceptable for a pivot, but unlogged).
Recommendation: Log a warning or throw when the role is missing; log pivot writes at debug.
Confidence: med.

18. [low/info] Dead DTOs + overbroad search-doc removal.
Description: Data/RegisterInput, ParticipantInput, CheckInInput carry no validation rules and are referenced nowhere in src, filament-events, or tests — dead code that will mislead the next author into thinking inputs are validated. EventSearchDocumentBuilder::remove(EventSearchDocument) with only event_id set deletes all docs for the event (all occurrences/sessions) — plausible intent but worth a comment/guard.
Recommendation: Delete or adopt+validate the DTOs; narrow remove() or document the cascade.
Confidence: med.

POSITIVES (verified)
- Owner scoping architecture is strong where applied: HasOwner+HasOwnerScopeConfig on 7 direct-owner models, ScopesByEventOwner (whereHas event chain + saving/deleting write guards via EventWriteGuard/OwnerWriteGuard) on ~40 children, EventSubmissionOwnerScope registered in provider; RegisterForFree/CreateRegistrationsFromOrder/CreateQuestion take lockForUpdate under the event/occurrence/session row inside transactions.
- Repo rules honored: uuid PKs everywhere; no FK constraints/cascades/SoftDeletes in migrations (only `foreignUuid()->index()` column types); money as bigInteger minor units (total_amount/unit_price/total_price); orchestration lives in Actions; no routes/widgets/jobs bypassing scopes except noted (BuildEventSearchDocumentJob correctly implements OwnerScopedJob with OwnerJobContext).
- Injection: no eval/exec/shell/unserialize; search LIKE uses bound parameters with allowlisted sort field/dir; whereRaw('1 = 0') constants only.
- XSS: both mail blades use {{ }} escaped output; welcome notification is ShouldQueue + afterCommit with owner context capture.
- Octane/cache: singletons (EventQueryService, RegistrationService, lifecycle/change-notice workflows) are stateless; taxonomy hierarchy caches in request attributes (request-scoped, Octane-safe); no static mutable state, no Cache:: stampede surface found.
- Indexes: events/registrations/attendances migrations index owner, slug, status, visibility, scope FKs, timestamps; registration_no unique.
- State machines: registration lifecycle timestamps recorded via initializeStatus/transitionStatus; lifecycle workflow uses transitionTo + domain events.
- Tests exist for the hot paths (RegisterForFree, PromoteInterested, RecordWalkIn/Headcount, AgentSale, CreateRegistrationsFromOrder, CheckInService, CrossTenantIsolation, OwnershipExceptionsMachineCheck, MigrationLint).

Out of scope / not verified: filament-events authorization + Filament navigation config (no Filament resources in this package); commerce-support HasOwner internals (auto-assign/overwrite semantics); whether console OwnerContext defaults make finding #10 fatal or merely inconvenient.

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### events (60+ models)
Bugs:
- `Event:162-172`, `EventSeries:43-49`, `EventTemplate:54-63`, `EventItinerary:40-46` HIGH — `owner_*/created_by_*/*_at/status` fillable (correction: children ALSO include owner).
- `Event` no cascade CRITICAL — no `booted/deleting`; orphans entire subtree.
- `RegistrationService:51-123,291-312` HIGH — `fill(Arr::except)` no Validator; `total_amount/currency/registrant_*/external_order_*/parent/pass_entitlements` mass-assignable; `createFromOrderItem` copies totals with `?? 0/'USD'`, no normalizer.
- `EventRegistrationItem:46-54` HIGH — `ticket_type_id` fillable, no same-event/owner validation.
Security:
- `ScopesByEventOwner:85-92` MEDIUM-verify (was HIGH blanket) — `whereNull(eventId) OR whereIn(subselect)`; leak depends on per-model matrix (models with `whereHas(event)` reject nulls).
- `EventSearchDocumentBuilder:188` LOW/MEDIUM-verify — `withoutGlobalScope('event_owner')` is indexer building cross-owner docs; needs ACL review, not auto HIGH.
- Filament occurrence/session/attendance/changelog inherit null-matrix above; same MEDIUM-verify.
Performance:
- Per-participant/answer/item `create()` loop in one txn MEDIUM — chunk or queue for 100+.
- No `paginate()` in `src` MEDIUM-verify — confirm cursor for list/search or large listings OOM. Check-in `lockForUpdate` GOOD.

---

### Prior-audit fix-first rows
| 23 | events | `Models/Event.php` (no booted cascade) | Delete orphans entire subtree | CRITICAL |
| 25 | events | `Services/RegistrationService.php:51-123,291-312` | Forged `total_amount/currency`, unvalidated `ticket_type_id`, no Validator | HIGH |
| — | events | `EventRegistrationItem:46-54` | `ticket_type_id` never validated same-event/owner | HIGH |

### Migration-batch rows (§8, code may already be fixed)
| 10 | orders cached totals + `recalculateTotals`/`getBalanceDue` (§4) | Columns folded into the orders create; model events are the single sync; ex-tax subtotal formula | `packages/orders/docs/04-usage.md` |

### Verification verdicts

- [R1:#1] CONFIRMED high bug | src/Actions/SyncEventClassificationsAction.php:73 | "delete-then-recreate classifications race": no txn/guard; find() per row; per-value firstOrCreate.
- [R1:#2] CONFIRMED high security | src/Models/Venue.php:65 | "nine models missing boundary": Venue/Taxonomy/Term/Role/etc lack owner traits; global firstOrCreate.
- [R1:#3] CONFIRMED high bug | src/Actions/PromoteInterestedToConfirmedAction.php:30 | "capacity check-then-act race": no lock/txn; agent ships inventory before registrations exist.
- [R1:#4] CONFIRMED high security | src/Services/RegistrationService.php:100 | "unvalidated items answers participants": ticket/prices verbatim; morph/status unchecked. DUP AUD:B3.
- [R1:#5] CONFIRMED medium bug | src/Actions/UpdateEventSessionAction.php:30 | "status bypasses state machine": fillable incl status; update() skips transitionTo checks.
- [R1:#6] CONFIRMED medium bug | src/Resolvers/DefaultEventRegistrationEligibility.php:18 | "eligibility ignores session event": occurrence-only early return; session/event unchecked.
- [R1:#7] CONFIRMED medium security | src/Services/DefaultEventCheckInService.php:95 | "arbitrary attendee morph attach": attendee_type/id, metadata/notes persisted unvalidated.
- [R1:#8] CONFIRMED medium performance | src/Actions/RegisterForFreeAction.php:160 | "idempotency scans entire scope": get()->filter on metadata; no index; hot-path linear.
- [R1:#9] CONFIRMED medium performance | src/Services/EventQueryService.php:21 | "unbounded result sets grow": findPublished/findByOwner get(); limit unclamped. DUP AUD:B9.
- [R1:#10] CONFIRMED medium bug | src/Console/Commands/FinalizeEventOrdersCommand.php:24 | "console lacks owner context": direct query; scope asserts owner; no --owner/--global.
- [R1:#11] CONFIRMED medium security | src/Policies/EventPolicy.php:15 | "single policy viewAny-true": sole Gate::policy; viewAny true; rest unregistered.
- [R1:#12] CONFIRMED medium bug | src/Actions/CloneEventContentsAction.php:60 | "unguarded partial clone risk": raw IDs; per-row saves; unknown relations skipped.
- [R1:#13] CONFIRMED high security | src/Models/Event.php:162 | "mass-assignable owner tuple": owner/created_by/status/timestamps fillable. DUP AUD:B1.
- [R1:#14] CONFIRMED low bug | src/Models/EventRegistration.php:98 | "bundle linkage unverified currency": parent/registrant unchecked; USD hardcoded vs MYR config.
- [R1:#15] CONFIRMED low bug | src/Models/EventRegistration.php:91 | "interested non-blocking plus slug": interested excluded; max(1,count); slug non-unique.
- [R1:#16] CONFIRMED low performance | database/migrations/2000_01_01_000014_create_event_registrations_table.php:18 | "missing composite capacity index": single-col only; (float) zero-checks smell-only.
- [R1:#17] CONFIRMED low bug | src/Actions/SyncManagementAssignmentToAuthzAction.php:58 | "silent missing-role no-op": returns silently; raw pivot write unlogged.
- [R1:#18] CONFIRMED low bug | src/Data/RegisterInput.php:10 | "dead DTOs overbroad removal": DTOs unreferenced in src; remove(event_id) wipes all docs.
- [AUD:B1] ADOPTED high bug | src/Models/Event.php:162 | owner/created_by/status/timestamps fillable; counted once. DUP R1:#13.
- [AUD:B2] ADOPTED critical bug | src/Models/Event.php:100 | no booted/deleting cascade; delete orphans subtree. DUP AUD:Q1.
- [AUD:B3] ADOPTED high bug | src/Services/RegistrationService.php:51 | fill(Arr::except) no Validator; totals/parent forgeable. DUP R1:#4.
- [AUD:B4] ADOPTED high bug | src/Models/EventRegistrationItem.php:46 | ticket_type_id fillable; never same-event/owner checked. DUP R1:#4.
- [AUD:B5] ADOPTED medium security | src/Models/Concerns/ScopesByEventOwner.php:85 | whereNull(eventId) OR whereIn(subselect); per-model matrix decides.
- [AUD:B6] ADOPTED medium security | src/Services/EventSearchDocumentBuilder.php:188 | withoutGlobalScope indexer builds cross-owner docs; needs ACL review.
- [AUD:B7] ADOPTED medium security | filament-events | occurrence/session/attendance inherit null-matrix; that pkg out of scope here.
- [AUD:B8] ADOPTED medium performance | src/Services/RegistrationService.php:77 | per-row create() in one txn; chunk/queue for 100+.
- [AUD:B9] ADOPTED medium performance | src/Services/EventQueryService.php:21 | no paginate() in src; cursor needed. DUP R1:#9.
- [AUD:Q1] ADOPTED critical bug | src/Models/Event.php:100 | delete orphans entire subtree; counted once. DUP AUD:B2.
- [AUD:Q2] ADOPTED high bug | src/Services/RegistrationService.php:51 | forged totals, unvalidated ticket_type_id. DUP AUD:B3.
- [AUD:Q3] ADOPTED high bug | src/Models/EventRegistrationItem.php:46 | ticket_type_id same-event/owner never checked. DUP AUD:B4.
- [AUD:Q4] UNVERIFIED low process | packages/orders/docs/04-usage.md | orders-pkg migration note; verify against orders sources (out of scope).

---

## feedback

### E2E findings (verbatim)

End-to-end review of packages/feedback (surveys/responses/invitations/analytics/testimonials). No tests dir, no routes dir, and empty src/Http in this package (verified via ls).

FINDINGS (severity | category | file:line | title — description | evidence | recommendation | confidence)

1. HIGH | bug | src/Actions/DuplicateFeedbackFormAction.php:38-77 | Duplicate drops section-less questions — only questions nested under sections are copied; questions with feedback_section_id=null are silently lost in the copy.
Evidence: `foreach ($source->sections as $section) { ... foreach ($section->questions ...` with no handling of unsectioned questions.
Recommendation: after the section loop, copy `$source->questions()->whereNull('feedback_section_id')` with their options. | high

2. HIGH | bug | src/Traits/ReceivesFeedback.php:41-57 | createFeedbackFormFromTemplate creates an EMPTY form — ignores the template definition (no sections/questions/options copied), bypasses CreateFeedbackFormFromTemplateAction (no reference guard, no FeedbackFormCreated event).
Evidence: `FeedbackForm::create([... 'name' => $template->name, 'purpose' => ...])` — definition never read.
Recommendation: delegate to CreateFeedbackFormFromTemplateAction with subject overrides, or replicate section/question/option creation. | high

3. HIGH | bug | src/Analytics/NpsCalculator.php:61-81, src/Analytics/CsatCalculator.php:63-83 | Per-question NPS/CSAT aggregates the wrong column — when $questionKey is given, whereHas filters responses but the CASE/COUNT/AVG still runs on feedback_responses.score (whole-response total), not the answer score for that question. Wrong whenever a form has >1 scored question.
Evidence: `->whereHas('answers', ... question key ...)` then `COUNT(CASE WHEN score >= 9 ...)` on the response query.
Recommendation: aggregate over FeedbackAnswer (join question, filter key) in the questionKey path. | high

4. HIGH | security | src/Actions/MarkFeedbackResponseAsSpamAction.php:11-21, ReviewFeedbackResponseAction.php:11-21, RejectFeedbackResponseAction.php:11-21, PublishFeedbackFormAction.php:13-27, CloseFeedbackFormAction.php:13-27, ArchiveFeedbackFormAction.php:13-27, MarkFeedbackInvitationOpenedAction.php:11-25, RejectFeedbackTestimonialAction.php:13-27, HideFeedbackTestimonialAction.php:13-24, ExtractFeedbackTestimonialAction.php:15-52 | Lifecycle mutations skip OwnerWriteGuard — these actions forceFill+save whatever model instance is passed, with no owner-context check, so a caller holding a cross-owner instance can mutate it. Inconsistent: Approve/Publish testimonial, delete, reorder, and submit-path actions DO guard.
Evidence: e.g. `$response->forceFill(['status' => 'spam', ...])->save();` with no guard call.
Recommendation: start each execute() with `OwnerWriteGuard::findOrFailForOwner(Model::class, $model->id)` and operate on the returned instance. | high

5. HIGH | bug | src/Actions/SubmitFeedbackResponseAction.php:139-149 | One-response-per-respondent bypassed after review + raceable — the exists() check only matches status='submitted', so after an admin reviews (status=reviewed) the respondent can submit again; there is also no unique DB constraint, so concurrent submits both pass the check.
Evidence: `->where('status', 'submitted')->exists()` then throw; no unique index in responses migration.
Recommendation: check `whereIn('status', ['submitted','reviewed'])` (decide rejected/spam policy) and add a unique index on (feedback_form_id, respondent_type, respondent_id) for opted-in forms, or lock on a respondent key. | high (bypass) / med (race)

6. MEDIUM | bug | src/Actions/CalculateFeedbackResponseScoreAction.php:26-30 | max_score computed as MAX(answer score) — `COALESCE(MAX(score),0) as max_score_val` takes the single largest answer score instead of the sum of per-question maxima (ScoreCalculator::calculateMaxScore exists but is unused here).
Recommendation: sum per-question maxima for the answered questions. | high

7. MEDIUM | bug | src/Analytics/FeedbackAnalyticsService.php:43-57 | pending_review duplicates completed_responses — both count `status='submitted'`; the "pending review" metric is meaningless.
Recommendation: define completed as submitted+reviewed and pending as submitted-only (or vice versa per product decision). | high

8. MEDIUM | bug | src/Support/ValidationRuleBuilder.php:73-87 | Choice answers never validated against defined options + Matrix/Likert misclassified — choiceRules only adds string/array/boolean, so arbitrary values pass and are stored (ScoreCalculator silently scores 0); Matrix/Likert are choice types but get the 'string' branch while real payloads are arrays, so legitimate matrix answers fail validation; FileUpload/Signature (disabled types) fall through with no type rule at all.
Recommendation: add `in:`/allowlist validation from question options for single/dropdown, per-element allowlist for multi/ranking; give Matrix/Likert array rules; reject disabled types. | high (allowlist) / med (matrix shape)

9. MEDIUM | bug | src/Actions/SendFeedbackInvitationAction.php:40-59 | Invitation never marked sent — creates status=Pending with sent_at=null, dispatches FeedbackInvitationCreated; the FeedbackInvitationSent event is never dispatched anywhere (grep confirmed) and no action transitions Pending→Sent.
Recommendation: set status=Sent + sent_at on send and dispatch FeedbackInvitationSent (or document Pending as terminal and remove the dead event). | high

10. MEDIUM | security | src/Actions/StartFeedbackResponseAction.php:24-33, src/Support/FeedbackModelReferenceGuard.php:14-44 | Respondent identity is caller-asserted, never auth-bound — any respondent_type (any Model subclass, no allowlist) + id that merely exists passes; nothing verifies the respondent is the authenticated user, enabling impersonation via the public submit path.
Evidence: `$this->referenceGuard->resolve($respondentType, $respondentId)` only checks existence/owner.
Recommendation: document that HTTP callers must bind respondent to auth()->user(); add an optional verified-respondent assertion; consider an allowlist of respondent classes. | med

11. MEDIUM | bug | src/Actions/SaveFeedbackFormStructureAction.php:48-93 | No question-key uniqueness or type validation — duplicate keys per form corrupt Submit (submittedValues keyed by key; validator rules keyed `answers.{key}` collide) and unknown/disabled types bypass QuestionTypeRegistry::isTypeAvailable.
Recommendation: enforce unique key per form (validation + unique index on feedback_questions(feedback_form_id,key)) and reject disabled/unknown types. | high (uniqueness gap) / med (impact)

12. MEDIUM | performance | src/Models/FeedbackForm.php:64-73, FeedbackResponse.php:63-69, FeedbackQuestion.php:59-65, FeedbackSection.php:45-50, FeedbackAnswer.php:50-55; src/Actions/DeleteFeedbackFormAction.php:20-50 | Cascades load everything into memory with N+1 deletes — model `deleting` hooks use `->each(fn => ->delete())` (one query per row, full collections hydrated); DeleteFeedbackFormAction plucks ALL question/response/answer ids and `->get()->each->delete()` testimonials unbounded (also risks whereIn parameter limits on huge forms).
Recommendation: chunk deletes (chunkById + query deletes where events aren't needed) or queue form deletion. | high

13. MEDIUM | performance | src/Support/ScoreCalculator.php:67-87 with SubmitFeedbackResponseAction.php:72-89; src/Analytics/FeedbackAnalyticsService.php:41-58 | Per-option queries in the submit loop + 7 round-trips per analytics calc — calculateChoiceScore issues one query per submitted option value inside the per-answer loop; calculateLive runs 7 separate count/avg queries.
Recommendation: preload options once per question (map value→score); collapse live aggregates into one selectRaw. | high

14. MEDIUM | bug | src/Actions/StartFeedbackResponseAction.php:24-64 | Start bypasses all form/invitation status checks — creates Draft responses on draft/closed/archived forms and with expired/cancelled/submitted invitations (Submit's guards don't apply to direct calls).
Recommendation: replicate assertFormAcceptingSubmissions/assertInvitationValid (minus one-response check) or route creation through a shared guard. | high

15. MEDIUM | bug+security | src/Actions/ExtractFeedbackTestimonialAction.php:15-52 | Testimonial extracted from first arbitrary text answer, unsanitized — picks the first non-empty text_value regardless of question type (could be an email/phone field), stores raw HTML/JS into a publicly publishable quote; also no OwnerWriteGuard and `$response->form` can fatal if the form is missing.
Evidence: `FeedbackAnswer::where(...)->whereNotNull('text_value')->first()` then `'quote' => $textAnswer->text_value`.
Recommendation: only extract from long_text/short_text questions flagged testimonial-eligible, strip tags + length-cap, guard owner, null-check form; document output-escaping for consumers. | med

16. MEDIUM | bug | database/migrations/*.php vs src/Models/*/getTable() | table_prefix config is ignored by ALL migrations — models prepend `feedback.database.table_prefix` but migrations (including via commerce_schema_create_if_missing, verified not to apply it) create unprefixed tables, so any non-empty prefix breaks the package. Migrations also lack down() methods.
Recommendation: apply the prefix in migrations (or remove the prefix feature); add down() or document non-rollback. | high (prefix) / low (down)

17. LOW | bug | src/Data/SubmitFeedbackResponseData.php:9-24 vs Submit/StartFeedbackResponseAction | Response metadata silently dropped — `$data->metadata` is accepted but never persisted (Start doesn't take it; forceFill only sets status/timestamps/ip/ua).
Recommendation: persist metadata on the response or remove the field. | high

18. LOW | security | src/Actions/ResolveFeedbackInvitationTokenAction.php:21-26 | Invitation rate limit keyed per-token-hash — each guessed token gets a fresh 60-attempt bucket, so the limiter throttles legitimate re-resolution, not enumeration. Impact is low (256-bit tokens: `bin2hex(random_bytes(32))`, sha256-hashed at rest, hash_equals-checked — good).
Recommendation: add an IP-keyed limiter alongside the token-keyed one. | high (mechanism) / low (impact)

19. LOW | bug | src/Analytics/FeedbackAnalyticsService.php:116-122 | Non-portable trend query — `DATE(submitted_at)` + `groupBy('date')` (reserved word) is MySQL-specific; breaks on pgsql/sqlite.
Recommendation: use a grammar-aware date cast or compute buckets in PHP. | med

20. LOW | bug | src/Analytics/FeedbackAnalyticsService.php:80-88 | nps()/csat() ignore their parameters — both take ($form, $questionKey) but return the bare calculator instances, a misleading API.
Recommendation: return `->calculate($form, $questionKey)` results or drop the params. | high

21. LOW | performance | database/migrations/2000_01_01_000005_create_feedback_responses_table.php | Missing index for the one-response check — no index on (feedback_form_id, respondent_type, respondent_id); forms.slug is unindexed/non-unique.
Recommendation: add the composite index (consider unique per finding 5) and index slug. | med

22. LOW | bug | src/Support/AnswerValueNormalizer.php:28-57 | Unsafe coercions on direct calls — `CarbonImmutable::parse($value)` throws on invalid dates (safe only via Submit because validation runs first); `(float)$value` silently coerces garbage ('abc'→0.0).
Recommendation: try/catch parse → null; validate numerics before cast. | med

23. LOW | bug | src/Actions/SubmitFeedbackResponseAction.php:91-96 | ip_address stored untruncated into a string column — an overlong X-Forwarded-For value causes a SQL error (500).
Recommendation: validate/truncate to 255 chars. | med

24. LOW | bug | src/Support/InvitationUrlGenerator.php:12-17 | Generated invitation URLs have no matching route — neither this package (no routes dir) nor filament-feedback (verified: no routes dir) defines `/{prefix}/invitations/{token}`; consuming apps must build it. Docs don't say so.
Recommendation: document the required app-side route (token → ResolveFeedbackInvitationTokenAction → form) or ship a route file. | high

POSITIVES (brief): owner-scoping is consistently applied (HasOwner+HasOwnerScopeConfig on all 10 models, OwnerWriteGuard on the submit/template/duplicate/delete/reorder paths, OwnerQuery on raw builders, owner-scoped analytics job + OwnerCache dashboard); invitation tokens are 256-bit, hashed at rest, constant-time compared; invitation expiry/cancel/reuse checks exist in both submit and resolve paths with lockForUpdate on the invitation; duplicate/template creation runs in transactions; publish-testimonial correctly requires approval + permission; migrations follow repo rules (uuid PKs, no FK constraints, sensible composite indexes); no Octane-unsafe mutable static state (QuestionTypeRegistry is pure); mass assignment is tight (owner_* excluded from $fillable).

### Prior-audit findings (verbatim chunk)

### Prior-audit section
### feedback
Bugs:
- DONE (2026-09-13, §8 item 3) — `SubmitFeedbackResponseAction:139-149` HIGH — `exists()` then insert, form lock only, no `(form,respondent)` unique → duplicates. Fixed: submitted-only partial unique folded into the responses create; idempotent Start (reuses drafts); full-transition rescue returns the winner; flag-off multi-submit still allowed.
- Invitation-expiry rollback MEDIUM — `assertInvitationValid:170-173` marks `Expired` then throws inside same txn → rolled back.
- `StartFeedbackResponseAction:42-59` MEDIUM — unlimited drafts, flips invitation to `Started` with no status check.
- `DeleteFeedbackFormAction:20-46` MEDIUM — `get()->each->delete()` N+1.
- No sweeper LOW; raw token in path LOW (`InvitationUrlGenerator` emits `/feedback/invitations/{rawToken}`; mitigated by `token_hash` unique + rate-limit).
Security:
- Anonymous vs guard HIGH-verify — `SubmitFeedbackResponseAction:34-52` requires `OwnerWriteGuard`; link-only anonymous with owner-mode on 403s absent explicit-global wiring, or leaks via enumeration if bypassed. Needs explicit test.
- `ResolveFeedbackInvitationTokenAction` returns full unscoped model MEDIUM — `email/phone/metadata` + `token_hash` (no `hidden`); audit callers for serialization.
Performance: recalc fan-out MEDIUM — `FeedbackAnalyticsService:95-156` per-view queries on nearly every submit; no debounce. Submit eager-loads well (`with(questions.options):35-38`) GOOD.

---

### Prior-audit fix-first rows
| 31 | feedback | `Actions/SubmitFeedbackResponseAction.php:139-149` | One-response check without lock/unique → duplicates | HIGH |

### Migration-batch rows (§8, code may already be fixed)
| 3 | feedback one-response unique (§3) | Submitted-only partial unique folded into the responses create (pgsql/sqlite; code-level on MySQL); idempotent Start; full-transition rescue; flag-off multi-submit kept | `packages/feedback/docs/04-usage.md`, `99-troubleshooting.md` |

### Verification verdicts

- [R1:#1] CONFIRMED high bug | src/Actions/DuplicateFeedbackFormAction.php:38 | "duplicate drops section-less questions": loops sections only; null-section questions lost.
- [R1:#2] CONFIRMED high bug | src/Traits/ReceivesFeedback.php:41 | "trait creates empty form": ignores template definition; bypasses guarded action+event.
- [R1:#3] CONFIRMED high bug | src/Analytics/NpsCalculator.php:61 | "per-question aggregates response score": whereHas filters but CASE/AVG run on responses.score.
- [R1:#4] CONFIRMED high security | src/Actions/MarkFeedbackResponseAsSpamAction.php:11 | "mutations skip OwnerWriteGuard": 10 actions forceFill+save; guard grep-absent.
- [R1:#5] DOWNGRADED (was HIGH) medium bug | src/Actions/SubmitFeedbackResponseAction.php:186 | "one-response bypass remains": race FIXED via partial unique; reviewed resubmit open. DUP AUD:B1.
- [R1:#6] CONFIRMED medium bug | src/Actions/CalculateFeedbackResponseScoreAction.php:26 | "max_score uses MAX answer": MAX(single) not sum of per-question maxima.
- [R1:#7] CONFIRMED medium bug | src/Analytics/FeedbackAnalyticsService.php:43 | "pending_review duplicates completed count": both count status=submitted.
- [R1:#8] CONFIRMED medium bug | src/Support/ValidationRuleBuilder.php:73 | "choice answers lack allowlist": no in: rules; Matrix/Likert get string branch.
- [R1:#9] CONFIRMED medium bug | src/Actions/SendFeedbackInvitationAction.php:40 | "invitation never marked sent": Pending+null sent_at; Sent event never dispatched.
- [R1:#10] CONFIRMED medium security | src/Actions/StartFeedbackResponseAction.php:24 | "respondent identity caller-asserted": any existing Model subclass passes; no auth bind.
- [R1:#11] CONFIRMED medium bug | src/Actions/SaveFeedbackFormStructureAction.php:48 | "question keys lack uniqueness": dup keys corrupt submit; no isTypeAvailable check.
- [R1:#12] CONFIRMED medium performance | src/Models/FeedbackForm.php:64 | "cascades hydrate then delete": deleting hooks each()->delete(); plucks all ids. DUP AUD:B4.
- [R1:#13] CONFIRMED medium performance | src/Support/ScoreCalculator.php:67 | "per-option queries plus round-trips": query per option value; live calc 7 queries. DUP AUD:B8.
- [R1:#14] CONFIRMED medium bug | src/Actions/StartFeedbackResponseAction.php:28 | "Start bypasses status checks": no form/invitation status validation on direct calls.
- [R1:#15] CONFIRMED medium bug | src/Actions/ExtractFeedbackTestimonialAction.php:15 | "testimonial uses arbitrary unsanitized answer": first text_value raw to public quote; no guard.
- [R1:#16] CONFIRMED medium bug | src/Models/FeedbackForm.php:87 | "migrations ignore table prefix": models prepend prefix, migrations don't; no down().
- [R1:#17] CONFIRMED low bug | src/Data/SubmitFeedbackResponseData.php:9 | "response metadata silently dropped": $data->metadata accepted, never persisted.
- [R1:#18] CONFIRMED low security | src/Actions/ResolveFeedbackInvitationTokenAction.php:21 | "rate limit keyed per-token": fresh bucket per guess; tokens 256-bit good.
- [R1:#19] CONFIRMED low bug | src/Analytics/FeedbackAnalyticsService.php:116 | "trend query MySQL-specific DATE": DATE()+groupBy('date') breaks pgsql/sqlite.
- [R1:#20] CONFIRMED low bug | src/Analytics/FeedbackAnalyticsService.php:80 | "nps csat ignore parameters": take form+key, return bare calculators.
- [R1:#21] CONFIRMED low performance | database/migrations/2000_01_01_000005_create_feedback_responses_table.php:54 | "one-response check lacks MySQL index": unique only pgsql/sqlite; slug unindexed.
- [R1:#22] CONFIRMED low bug | src/Support/AnswerValueNormalizer.php:28 | "normalizer coerces unsafe garbage": parse() throws; (float) maps 'abc' to 0.
- [R1:#23] CONFIRMED low bug | src/Actions/SubmitFeedbackResponseAction.php:116 | "ip_address stored untruncated string": overlong XFF value causes SQL 500.
- [R1:#24] CONFIRMED low bug | src/Support/InvitationUrlGenerator.php:12 | "invitation URLs lack matching route": no Route:: in pkg or filament-feedback; app builds it.
- [AUD:B1] FIXED high bug | database/migrations/2000_01_01_000005_create_feedback_responses_table.php:73 | partial unique+idempotent Start+rescue verified. DUP R1:#5 race.
- [AUD:B2] ADOPTED medium bug | src/Actions/SubmitFeedbackResponseAction.php:222 | expiry mark then throw inside txn rolls back; Resolve path persists.
- [AUD:B3] ADOPTED medium bug | src/Actions/StartFeedbackResponseAction.php:127 | draft-reuse FIXED 09-13; invite to Started skips status check. DUP R1:#14.
- [AUD:B4] ADOPTED medium performance | src/Actions/DeleteFeedbackFormAction.php:20 | get()->each->delete() N+1; counted once. DUP R1:#12.
- [AUD:B5] ADOPTED low bug | src/Support/InvitationUrlGenerator.php:12 | no sweeper in src; raw token URL mitigated by hash-unique+limits.
- [AUD:B6] UNVERIFIED high security | src/Actions/SubmitFeedbackResponseAction.php:47 | needs HTTP test: anonymous submit with feedback.owner enabled (403 vs leak).
- [AUD:B7] ADOPTED medium security | src/Actions/ResolveFeedbackInvitationTokenAction.php:32 | returns full unscoped model; no $hidden on token_hash/contacts.
- [AUD:B8] ADOPTED medium performance | src/Analytics/FeedbackAnalyticsService.php:41 | per-view recalc, no debounce; counted once. DUP R1:#13.
- [AUD:Q1] FIXED high bug | src/Actions/SubmitFeedbackResponseAction.php:137 | duplicate rescue returns winner; counted once. DUP AUD:B1.
- [AUD:Q2] FIXED high bug | database/migrations/2000_01_01_000005_create_feedback_responses_table.php:34 | unique folded into create; idempotent Start verified.

---

## filament-addressing

### E2E findings (verbatim)

End-to-end review: packages/filament-addressing (Filament v5 adapter over core `addressing`; no migrations/routes/jobs/commands/widgets in package — correct per adapter-only guardrail).

FINDINGS

1) [medium | bug | src/Resources/AddressResource/Pages/CreateAddress.php:25 | CreateAddress ignores configured model override | high]
Description: `handleRecordCreation` hardcodes `$address = new Address;` while `AddressResource::getModel()` honors `filament-addressing.resources.addresses.model`. A host overriding the model gets the base class on create (wrong table/behavior) but the custom class everywhere else.
Evidence: `$address = new Address; $address->fill($data); $address->save();` vs `AddressResource::getModel()` returning `config('...addresses.model', Address::class)`.
Recommendation: Resolve via `$model = AddressResource::getModel(); $address = new $model;` (same pattern `AddressAreaImporter::resolveRecord()` uses).

2) [medium | bug+security | src/Exports/AddressExporter.php:14 | AddressExporter hardcodes model and has no owner scoping | high (hardcoded model) / med (scope leak)]
Description: Unlike `AddressAreaExporter`/`AddressCountryExporter` (which override `getModel()` from config), `AddressExporter` hardcodes `protected static ?string $model = Address::class`. It also defines no query scoping, while `Address` is owner-scoped (`HasOwner`) and the resource applies `OwnerUiScope::apply(..., includeGlobal: false)` — exports may bypass the resource's owner scope depending on Filament's exporter query path. Mitigated by default: `features.address_export` is false.
Evidence: `protected static ?string $model = Address::class;` with no `getModel()`/`modifyQuery` override.
Recommendation: Override `getModel()` from config like the sibling exporters, and scope the export query with `OwnerUiScope` (e.g. `modifyQueryUsing`/`modifyQuery` hook) + a test asserting cross-owner rows are excluded.

3) [medium | bug | src/Resources/PostalCodeResource/Pages/ListPostalCodes.php:20-27 | Postcode import/export actions have no importer/exporter and config key is missing | med]
Description: `ImportAction::make()->label('Import Postcodes')` and `ExportAction::make()->label('Export Postcodes')` specify no `->importer()`/`->exporter()` class, so enabling them throws at runtime. `features.postal_code_import` does not even exist in default config (only `postal_code_export`, false). Compare `ListAddressAreas`, which wires both classes.
Evidence: lines 20–27 vs `ListAddressAreas` `->importer(AddressAreaImporter::class)` / `->exporter(AddressAreaExporter::class)`.
Recommendation: Add the missing importer/exporter classes (or remove the actions), and add both keys to default config.

4) [medium | performance | src/Tables/AddressAreaTable.php:100-143 | Uncached distinct scans over the areas table on every table render | high (code) / med (impact)]
Description: Each render runs 4 uncached `distinct` queries over all areas (`type`, `level`, `source` options + `country_code` filter pluck at lines 74–82) plus a distinct scan of `address_area_roles`. Imported area datasets can be large, making every list view pay full-table scans.
Evidence: `getTypeOptions()/getLevelOptions()/getSourceOptions()` each do `$areaClass::query()->distinct()->orderBy(...)->pluck(...)`.
Recommendation: Cache option lists with a short TTL (invalidate on import/save), or convert to `relationship()`/static-enum filters.

5) [medium | performance | src/Tables/AddressAreaTable.php:26,43,54; src/Tables/PostalCodeTable.php:20; src/Tables/AddressCountryTable.php:29 | Relationship columns with no explicit eager loading (N+1 risk) | med]
Description: List columns traverse relations per row (`names.name`, `roles.role`, `parent.name`, `areas.name`, `currencies.code`, `country/state.name`) but no table applies `->with([...])` via `modifyQueryUsing`. Each page of N rows can issue O(N) extra queries unless Filament auto-eager-loads the path.
Evidence: `TextColumn::make('names.name')->listWithLineBreaks()`, `->make('roles.role')`, `->make('parent.name')`, `->make('areas.name')` with no `with()` anywhere in `src/Tables`.
Recommendation: Add explicit eager loads (`names`, `roles`, `parent`, `areas`, `currencies`, `country`, `state`) per table and verify query counts.

6) [medium | performance | src/Schemas/AddressFormSchema.php:67-77 (+34-42) | State/country option loading is unbounded and uncached | high]
Description: The `state_id` options closure only filters `when($get(country_code))` — with no country selected it plucks the entire global states table into the dropdown on initial render. Country options (here and `AddressAreaFormSchema.php:34-40`) hydrate full `AddressCountry` models via `->get()` + `mapWithKeys` on every form render instead of `pluck`, with no caching (~250 rows × per render).
Evidence: `->options(fn... => ModelResolver::stateClass()::query()->when($get(...), ...)->orderBy('name')->pluck('name','id')->toArray())`.
Recommendation: Return `[]` when country is blank (field is hidden then anyway via `countryHasStates`), switch country loads to `pluck`, and consider caching the country list.

7) [low | bug | src/Schemas/AddressFormSchema.php:140-145 | LIKE wildcards in area search are not escaped | high]
Description: `"%{$search}%"` interpolates raw user input into `like`/`ilike`, so `%`/`_` act as wildcards (e.g. `%%%` matches everything up to the limit) — wrong results and wasted scans.
Evidence: `->where('name', $operator, "%{$search}%")->orWhere('slug', ...)->orWhere('code', ...)`.
Recommendation: Escape `%`, `_`, `\` in `$search` before interpolating.

8) [low | bug | src/Resources/PostalCodeResource/Pages/CreatePostalCode.php:10, EditPostalCode.php:10 | Postcode create/edit pages lack the read-only canAccess guard | high]
Description: Every other editable page (`CreateAddress`, `EditAddress`, area/country/state/city edits) overrides `canAccess()` to return `! Resource::isReadOnly()`; the postcode pages omit it. Routes are withheld via `getPages()` when read-only, so this is defense-in-depth inconsistency, not an open hole.
Recommendation: Add the same `canAccess()` override.

9) [low | bug (dormant security note) | src/Support/GuardsAddressingUi.php:1, src/Support/ResolvesAddressingResources.php:1, src/Resources/AddressAreaResource.php:68-71, src/RelationManagers/AddressesRelationManager.php:15 | Dead code incl. an unregistered, owner-unscoped relation manager | high]
Description: `GuardsAddressingUi` and `ResolvesAddressingResources` are never referenced; `AddressAreaResource::getEloquentQuery()` is a no-op passthrough. `AddressesRelationManager` is registered nowhere — and if wired in, its `AttachAction::make()->preloadRecordSelect()` and `EditAction` would operate on owner-scoped `Address` records without any `OwnerUiScope` check (cross-owner attach/edit IDOR).
Recommendation: Delete the unused support types and no-op override; either delete `AddressesRelationManager` or scope its record select/actions with `OwnerUiScope` before registering.

10) [low | bug | src/Resources/AddressCountryResource.php:136-140 | ISO2 accepts 1-char values | high]
Description: `iso2` has `maxLength(2)` but no minimum/`size:2`/alpha rule, so a 1-char code passes (only reachable when `features.country_editing` is enabled; default read-only).
Recommendation: Add `->minLength(2)` (or `size:2` + `alpha`) matching ISO 3166-1 alpha-2.

11) [low | process | packages/filament-addressing/tests (missing) | Package has zero tests | high]
Description: Verified `tests/` does not exist. Owner-scoping, validation rules, importer/exporter wiring, and read-only gating are all untested in-package.
Recommendation: Add Pest coverage at minimum for: owner scoping of Address/Snapshot queries, `canAccess` read-only gating, the two validation rules, and exporter model/scope behavior.

POSITIVES (brief)
- `AddressResource` and `AddressSnapshotResource` correctly apply `OwnerUiScope::apply(..., includeGlobal: false)`; geography resources intentionally global per CONTEXT.
- Submitted IDs revalidated server-side (`StateBelongsToCountry`, `AddressAreasBelongToCountry`) AND core-side (`SyncAddressAreaAssignmentsAction` re-checks country/active; `AddressOwnerGuard::assertAddressIsWritable`).
- Orchestration delegated to core actions (`SaveAddressAreaAction`, `SyncAddressAreaAssignmentsAction`, `ImportAddressAreasAction`); create-path parent validation confirmed present in `SaveAddressAreaAction`.
- Mass assignment safe: `Address::$fillable` excludes `owner_type/owner_id`; `HasOwner@creating` auto-fills owner from `OwnerContext` and fails closed.
- No XSS vectors: no `->html()`/`->markdown()`; `formatStateUsing` outputs plain JSON text; `->url()` targets internal `getUrl()` only.
- No SSRF/path-traversal/deserialization sinks; importer metadata JSON uses `JSON_THROW_ON_ERROR` → `RowImportFailedException`.
- Delete copy matches reality: core `AddressArea@deleting` nulls children's `parent_id`.
- Bounded pagination (`[10,25,50,100]`), search `limit(50)`, request-attribute (Octane-safe) resolver caching, config-driven navigation via `getNavigationGroup`.
- Checked with no findings: N/A correctly — no migrations (no FK question), no money fields, no SoftDeletes, no jobs/commands/widgets/caches to stampede.

### Prior-audit findings (verbatim chunk)

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-addressing` MEDIUM — `AddressAreaResource:68-70` bare parent vs scoped siblings; `PostalCodeResource:89-94` areas filter only `country_code`.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

### Verification verdicts

- [R1:#1] CONFIRMED medium bug | src/Resources/AddressResource/Pages/CreateAddress.php:25 | "CreateAddress ignores model override": hardcodes new Address vs getModel config.
- [R1:#2] CONFIRMED medium bug | src/Exports/AddressExporter.php:14 | "exporter hardcodes model unscoped": static $model; no getModel/scope; flag mitigates.
- [R1:#3] CONFIRMED medium bug | src/Resources/PostalCodeResource/Pages/ListPostalCodes.php:20 | "postcode actions lack importer exporter": no importer/exporter classes; import key missing.
- [R1:#4] CONFIRMED medium performance | src/Tables/AddressAreaTable.php:100 | "uncached distinct scans render": 4+ distinct queries per render; no cache.
- [R1:#5] CONFIRMED medium performance | src/Tables/AddressAreaTable.php:26 | "relation columns lack eager loads": names/roles/parent per-row; no with() in src. DUP AUD:B4.
- [R1:#6] CONFIRMED medium performance | src/Schemas/AddressFormSchema.php:67 | "state country options unbounded": no-country plucks all states; get()+map uncached.
- [R1:#7] CONFIRMED low bug | src/Schemas/AddressFormSchema.php:140 | "area search unescaped wildcards": raw %search% in like/ilike; % matches all.
- [R1:#8] CONFIRMED low bug | src/Resources/PostalCodeResource/Pages/CreatePostalCode.php:10 | "postcode pages lack guard": no canAccess vs siblings; routes withheld anyway.
- [R1:#9] CONFIRMED low bug | src/RelationManagers/AddressesRelationManager.php:15 | "dead code unscoped manager": 2 unused types; no-op query; unregistered unscoped RM.
- [R1:#10] CONFIRMED low bug | src/Resources/AddressCountryResource.php:136 | "ISO2 accepts one-char values": maxLength(2) only; no min/size/alpha rule.
- [R1:#11] CONFIRMED low process | tests (missing) | "package ships zero tests": tests/ dir absent (path error); scoping/gating untested.
- [AUD:B1] ADOPTED medium security | src/Resources/AddressAreaResource.php:68 | bare parent query vs scoped siblings; areas filter country-only (PostalCode:89).
- [AUD:B2] ADOPTED low process | src/Resources | G1 navigation PASS; no static $navigationGroup in src.
- [AUD:B3] FALSE medium performance | src/Resources | no getNavigationBadge anywhere in pkg; G2 COUNT claim N/A here.
- [AUD:B4] ADOPTED medium performance | src/Tables/AddressAreaTable.php:26 | relation TextColumns without with(); counted once. DUP R1:#5.
- [AUD:B5] ADOPTED medium performance | src/Schemas/AddressFormSchema.php:32 | unbounded select-option loads cluster; counted once. DUP R1:#6.
- [AUD:B6] ADOPTED low process | src | no addressing leakage instance cited; MoneyHelper absent; residual caution.
- [AUD:B7] ADOPTED low performance | src | no addressing sums instance cited; cashier/inventory only; N/A here.

---

## filament-affiliate-network

### E2E findings (verbatim)

End-to-end review of packages/filament-affiliate-network (Filament v5 adapter; no routes/migrations/jobs/commands/tests in package — UI + policies + support only; domain lives in affiliate-network). Owner scoping is opt-in (affiliate-network.owner.enabled and affiliates.owner.enabled both default false), but this package explicitly supports enabled=true (Create/Edit pages wrap writes in OwnerContext::withOwner(null)), so scope findings below are real bugs in that mode.

FINDINGS

[H1] bug, HIGH — Verify-site action re-fetch misses scope bypass → 404 on every owned site
File: src/Resources/AffiliateSiteResource/Tables/AffiliateSitesTable.php:71-73
Evidence: `$scopedRecord = OwnerContext::withOwner(null, fn () => AffiliateSite::query()->whereKey(...)->firstOrFail());` — no `->withoutOwnerScope()`. OwnerQuery::applyToEloquentBuilder with null owner constrains to `whereNull(owner_type,id)` (commerce-support/src/Support/OwnerQuery.php:40-43), so with owner.enabled=true this finds only global rows; any owned site throws ModelNotFoundException. Sibling code (OptionsProvider, offer activate/pause) always pairs withOwner(null) with scope removal.
Recommendation: add `->withoutOwnerScope()` to the re-fetch, matching AffiliateOfferResource::getEloquentQuery. Confidence: high.

[H2] bug, HIGH — Admin approve/reject/revoke/bulk-approve fail cross-tenant when affiliates.owner.enabled=true
File: src/Resources/AffiliateOfferApplicationResource/Tables/AffiliateOfferApplicationsTable.php:79-133,144-158
Evidence: table lists all applications via getEloquentQuery()->withoutGlobalScope(ScopesByBelongsToOwner), but approve/reject/revoke call OfferManagementService with the ambient context. Core re-queries with default scope: ApproveApplication::execute does `AffiliateOfferApplication::query()->whereKey(...)->firstOrFail()` and reject/revoke do the same (affiliate-network/src/Actions/ApproveApplication.php, Services/OfferManagementService.php:145-147,165-167). ScopesByBelongsToOwner scopes via affiliate owner ('affiliates.owner'), so cross-owner rows 404, or NoCurrentOwnerException if no owner resolves. Marketplace flow works only because it wraps calls in withAffiliateOwnerContext.
Recommendation: add an explicit-global/admin path in affiliate-network (service accepts bypass or wraps in withOwner(null)+withoutGlobalScope) and call it here; per CONTEXT guardrails the fix belongs in core, this package stays UI-only. Confidence: high.

[H3] bug, HIGH — `affiliate.email` column searchable+sortable on a virtual accessor → SQL error
File: src/Resources/AffiliateOfferApplicationResource/Tables/AffiliateOfferApplicationsTable.php:34-37
Evidence: `TextColumn::make('affiliate.email')->searchable()->sortable()`. Affiliate::email is an Eloquent Attribute accessor over contact_methods (affiliates/src/Models/Affiliate.php:410-420+), and no affiliates migration creates an `email` column (only a creative-type comment mentions 'email'). Filament generates whereHas/order queries against a nonexistent `email` column → QueryException when a user searches or sorts by Email.
Recommendation: drop searchable()/sortable() on that column, or point search at a real relation/column (e.g. contactMethods value). Confidence: high.

[M1] bug, MEDIUM — Merchant dashboard uses ambient owner scope on a network-global admin page
File: src/Pages/MerchantDashboardPage.php:91-139
Evidence: getSitesCount/getVerifiedSitesCount/getActiveOffersCount/getPendingApplicationsCount/getRecentApplications/getTopOffers all query with default scopes, while the page is gated by NetworkAdminAccess (global) and the sibling NetworkStatsAggregator uses OwnerContext::withOwner(null)+scope bypass. With owner.enabled=true the dashboard shows single-tenant (or global-only/empty) numbers, or throws NoCurrentOwnerException — inconsistent with the widgets on the same screen.
Recommendation: mirror NetworkStatsAggregator (explicit global + withoutOwnerScope/withoutGlobalScope) or reuse it. Confidence: high.

[M2] performance, MEDIUM — Marketplace N+1: per-offer status query × up to 50 rows
File: src/Pages/AffiliateMarketplacePage.php:173-183; resources/views/pages/affiliate-marketplace.blade.php:82
Evidence: blade calls `$this->getApplicationStatus($offer)` per card; each call runs OfferManagementService::applicationStatusForOffer (linkedProgram lookup + application value query, plus membership query for program offers) inside withAffiliateOwnerContext. getOffers() caps at 50 with no pagination → ~50–150 extra queries per render.
Recommendation: batch-fetch one status map (offer_id → status) for the rendered page in getOffers() and read from it in the blade. Confidence: high.

[M3] performance, MEDIUM — Unbounded pluck()->all() option lists in selects
File: src/Support/AffiliateNetworkOptionsProvider.php:16-52; used by AffiliateOfferForm.php:29-39, AffiliateOfferCategoryForm.php:26-32
Evidence: verifiedSiteOptions/activeCategoryOptions/parentCategoryOptions load entire tables into Filament Select options (searchable() then filters client-side). Grows linearly with catalog size; also unordered (parent list).
Recommendation: switch site/category/parent fields to relationship() selects with async search (plus existing firstOrFail ID revalidation), or paginate/limit options. Confidence: high.

[M4] bug, MEDIUM — Offer form validation gaps; duplicate slug → raw 500
File: src/Resources/AffiliateOfferResource/Schemas/AffiliateOfferForm.php:47-49,64-84,132-136
Evidence: `slug` is required but has no unique rule, while migration enforces `unique(['site_id','slug'])` (000003). Duplicates surface as QueryException, not a validation error (contrast: site domain and category slug both use unique()). Also: rate_base_bp/rate_fixed_minor are `numeric()` but feed int minor-unit columns (decimals/negatives accepted — money-as-int rule violated at input); currency only maxLength(3); cookie_days unbounded; ends_at may precede starts_at.
Recommendation: add unique(site_id,slug,ignoreRecord) rule, ->integer()->min(0) (+max for bp) on rate fields, size:3/alpha on currency, min(0) on cookie_days, ends_at after-or-equal starts_at. Confidence: high.

[M5] bug, MEDIUM — Category parent assignment allows hierarchy cycles
File: src/Resources/AffiliateOfferCategoryResource/Schemas/AffiliateOfferCategoryForm.php:26-32; Pages/CreateAffiliateOfferCategory.php, EditAffiliateOfferCategory.php mutateFormDataBefore*
Evidence: parent options exclude only the record itself (`excludeId`), and mutate* only revalidates existence. Setting parent to a descendant creates a cycle (ancestor traversal loops). No descendants check anywhere in package or (checked) form path.
Recommendation: validate parent is not a descendant of the record (walk parent chain in core or a rule) — core-side rule preferred per adapter-only guardrail. Confidence: high.

[M6] security, MEDIUM — Affiliate identity resolved by unverified email match
File: src/Pages/AffiliateMarketplacePage.php:128-156
Evidence: getAffiliate() matches `$user->email` (fallback when getEmail() absent) against affiliate contact_methods with no email-verification check, then applyForOffer()/generateLink() act as that affiliate. If the host app lets users change emails without verification, an attacker can claim a victim affiliate's identity and generate tracking links as them.
Recommendation: require verified email (e.g. hasVerifiedEmail / email_verified_at) before resolving, or bind affiliate to user id explicitly. Confidence: med (exploitability depends on host user model).

[M7] bug, MEDIUM — Offer activate/pause bypass the domain service + write outside owner context
File: src/Resources/AffiliateOfferResource/Tables/AffiliateOffersTable.php:100-125
Evidence: actions fetch inside `OwnerContext::withOwner(null)` but call `$scopedRecord->update(['status'=>...])` outside it, and mutate status directly instead of via OfferManagementService/Actions — violating the package's adapter-only guardrail (skips transitions, validation, events, notifications; risks ScopesByBelongsToOwner updating-hook throw when enabled).
Recommendation: move publish/archive transitions into affiliate-network Actions and call them here (wrapped in the same explicit-global context the core action expects). Confidence: high.

[L1] security, LOW — Marketplace page has no canAccess gate or rate limiting
File: src/Pages/AffiliateMarketplacePage.php (whole; contrast MerchantDashboardPage.php:32-45)
Evidence: every resource, both widgets, and MerchantDashboardPage gate on NetworkAdminAccess; the marketplace exposes cross-tenant offers plus state-changing applyForOffer($offerId,$reason)/generateLink($offerId) Livewire actions to any panel user with no throttle and no $reason length cap.
Recommendation: if public-by-design, document it and add rate limits + reason maxLength; otherwise add canAccess(). Confidence: med.

[L2] bug, LOW — getReviewerName can TypeError under strict types
File: src/Resources/AffiliateOfferApplicationResource/Tables/AffiliateOfferApplicationsTable.php:164-176
Evidence: declared `?string` return but returns `$user->name ?? $user->getAuthIdentifier()` (getAuthIdentifier returns mixed, commonly int) and `getName()` mixed. With strict_types=1 a non-string return is a TypeError.
Recommendation: cast: `(string) (...)`. Confidence: med.

[L3] performance, LOW — Merchant dashboard blade runs each query twice
File: resources/views/pages/merchant-dashboard.blade.php:37,43,69,75
Evidence: getRecentApplications() and getTopOffers() each invoked twice (isEmpty + foreach); getStats() runs 4 count queries.
Recommendation: compute once in mount()/cache property. Confidence: high.

[L4] bug, LOW — Site form: no domain format/normalization; status transitions unrestricted
File: src/Resources/AffiliateSiteResource/Schemas/AffiliateSiteForm.php:28-62
Evidence: domain is required+unique but accepts `http://…`/uppercase/whitespace despite helper text; status select permits any transition (e.g. straight to Verified without verification, or away from Verified leaving verified_at stale).
Recommendation: add domain rule + lowercase/trim normalization; route verification through SiteVerificationService or constrain transitions. Confidence: med.

[L5] bug, LOW — NetworkStatsAggregator: uncast sums + hardcoded USD
File: src/Support/NetworkStatsAggregator.php:25-27,44
Evidence: `sum()` returns mixed (driver-dependent int|string) but is passed to formatMinor(int) under strict_types=1 and declared int in the shape; revenue is formatted as 'USD' while offers carry per-record currency (TopOffersWidget already does per-record currency correctly).
Recommendation: cast `(int)` on sums; note multi-currency limitation or aggregate per currency. Confidence: med.

[L6] bug, LOW — TopOffersWidget user sorting applies only within cached top-10-by-clicks
File: src/Widgets/TopOffersWidget.php:32-61
Evidence: ID list cached ordered by clicks; table then `whereKey($ids)` with sortable conversions/revenue columns — re-sorting reorders only the cached 10, silently misleading.
Recommendation: disable column sorting or make sort invalidate/rebuild the ID cache. Confidence: med.

POSITIVES (brief): all Blade output escaped (no {!! !!}); marketplace search is parameterized with %/_ escaping, 3-char minimum, driver-aware like/ilike, and limit(50); resources eager-load relations and bypass scopes explicitly with clear comments; submitted site/category/parent IDs revalidated via whereKey+firstOrFail in Create/Edit mutators; NetworkAdminAccess fails closed (false on empty ability/anonymous user) with super-admin support via FilamentPermission; expensive widgets cached 30s via OwnerCache; money display via MoneyFormatter; nav via config navigation.group + getNavigationGroup per repo rules; no migrations/FKs/SoftDeletes; orchestration mostly via OfferManagementService/OfferLinkService; no Octane-unsafe static request state (OwnerContext is request-scoped; page memoization is instance-level).

ALSO NOTED: package ships zero automated tests (no tests/ dir; only docs/08-testing.md). No mass-assignment, SSRF, path-traversal, deserialization, or injection vectors found in this package's own code; XSS surface is escaped output only.

### Prior-audit findings (verbatim chunk)

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-affiliate-network` HIGH*-verify → LOW if gate proven — 4 resources `withoutOwnerScope/withoutGlobalScope` gated only by `NetworkAdminAccess::allows()` + `admin_ability` config; confirm admin-only.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

### Verification verdicts

- [R2:H1] CONFIRMED high bug | src/Resources/AffiliateSiteResource/Tables/AffiliateSitesTable.php:71 | withOwner(null) re-fetch lacks withoutOwnerScope; owned sites 404 when owner on.
- [R2:H2] CONFIRMED high bug | src/Resources/AffiliateOfferApplicationResource/Tables/AffiliateOfferApplicationsTable.php:79 | admin actions use ambient ctx; core re-queries scoped; cross-tenant 404.
- [R2:H3] CONFIRMED high bug | src/Resources/AffiliateOfferApplicationResource/Tables/AffiliateOfferApplicationsTable.php:34 | affiliate.email is virtual accessor yet searchable; search errors (sortable gone).
- [R2:M1] CONFIRMED medium bug | src/Pages/MerchantDashboardPage.php:91 | global admin page uses ambient scopes; counts throw or mis-scope when owner on.
- [R2:M2] CONFIRMED medium performance | src/Pages/AffiliateMarketplacePage.php:173 | per-card getApplicationStatus hits service x50 rows; blade:82; no pagination.
- [R2:M3] CONFIRMED medium performance | src/Support/AffiliateNetworkOptionsProvider.php:16 | pluck()->all() unbounded unordered option lists. DUP AUD:B5.
- [R2:M4] CONFIRMED medium bug | src/Resources/AffiliateOfferResource/Schemas/AffiliateOfferForm.php:47 | slug no unique rule vs DB unique(site,slug); numeric rates; unbounded fields.
- [R2:M5] CONFIRMED medium bug | src/Resources/AffiliateOfferCategoryResource/Schemas/AffiliateOfferCategoryForm.php:26 | parent excludes self only; mutators existence-only; cycles possible.
- [R2:M6] CONFIRMED medium security | src/Pages/AffiliateMarketplacePage.php:128 | affiliate resolved by email match; no verified-email check; host-dependent.
- [R2:M7] CONFIRMED medium bug | src/Resources/AffiliateOfferResource/Tables/AffiliateOffersTable.php:100 | activate/pause update outside owner ctx; bypass domain service/events.
- [R2:L1] CONFIRMED low security | src/Pages/AffiliateMarketplacePage.php:185 | no canAccess/throttle; state-changing actions; uncapped reason; public-by-design?
- [R2:L2] CONFIRMED low bug | src/Resources/AffiliateOfferApplicationResource/Tables/AffiliateOfferApplicationsTable.php:164 | ?string returns mixed id/getName; strict_types TypeError risk.
- [R2:L3] CONFIRMED low performance | resources/views/pages/merchant-dashboard.blade.php:37 | getRecentApplications/getTopOffers each called twice (isEmpty+foreach).
- [R2:L4] CONFIRMED low bug | src/Resources/AffiliateSiteResource/Schemas/AffiliateSiteForm.php:28 | domain no format/normalization; any status transition; verified_at stale.
- [R2:L5] CONFIRMED low bug | src/Support/NetworkStatsAggregator.php:25 | sum() mixed into formatMinor(int) strict; revenue hardcoded USD; multi-currency.
- [R2:L6] CONFIRMED low bug | src/Widgets/TopOffersWidget.php:32 | sort reorders cached top-10-by-clicks only; silently misleading.
- [AUD:B1] ADOPTED low security | src/Support/NetworkAdminAccess.php:12 | 4 resources+policies+pages gate on allows(); fails closed on empty ability.
- [AUD:B2] ADOPTED low process | src | G1 navigation PASS; no static $navigationGroup in src.
- [AUD:B3] FALSE medium performance | src | no getNavigationBadge anywhere in pkg; G2 COUNT claim N/A here.
- [AUD:B4] FALSE medium performance | src/Resources/AffiliateOfferResource.php:78 | resources/pages/widgets all eager-load; G3 claim N/A here.
- [AUD:B5] ADOPTED medium performance | src/Support/AffiliateNetworkOptionsProvider.php:16 | unbounded pluck options; counted once. DUP R2:M3.
- [AUD:B6] ADOPTED low process | src | no pkg leakage instance cited; TopOffers formats per-record; residual caution.
- [AUD:B7] ADOPTED low performance | src/Widgets/TopOffersWidget.php:57 | TopOffers already withSum; no other pkg instance; N/A here.

---

## filament-affiliates

### E2E findings (verbatim)

Review of packages/filament-affiliates (Filament adapter, no models/migrations/routes/tests of its own — correct per CONTEXT.md guardrails).

FINDINGS

1) severity: high | category: bug/security | file: src/Resources/AffiliateResource/Schemas/AffiliateForm.php:196-197
Title: Hidden owner_type/owner_id are dehydrated — arbitrary owner assignment via form tampering
Description: The "Portal Access" section uses `Hidden::make('owner_type')` / `Hidden::make('owner_id')` which are dehydrated by default, so a crafted request can set any owner_type/owner_id directly on the Affiliate, bypassing the linked_user afterStateUpdated logic. There is no validation of the morph class or of the target user id.
Evidence: `Hidden::make('owner_type'), Hidden::make('owner_id'),` with no `->dehydrated(false)`; `linked_user` is `->dehydrated(false)` but the Hiddens carry the values.
Recommendation: Make both Hiddens `dehydrated(false)` and resolve owner server-side in Create/Edit pages (validate user exists), or add strict `Rule::in` on owner_type + exists rule on owner_id.
Confidence: high

2) severity: high | category: security | file: src/Actions/UpdateAffiliateFraudSignalStatus.php:27-29 (same in src/Actions/BulkFraudReviewAction.php:63-65)
Title: Fraud-signal status change re-fetches record without owner scope — cross-owner write
Description: Both actions re-query via unscoped `AffiliateFraudSignal::query()->whereKey(...)->firstOrFail()`, while the resource list itself is owner-scoped via whereHas(affiliate). The Gate `update` policy checks only permissions, not ownership. A user with fraud.update permission can mutate a signal from another owner by submitting its id. Payout actions in the same package correctly use OwnerWriteGuard; these do not.
Evidence: `$signal = AffiliateFraudSignal::query()->whereKey($record->getKey())->firstOrFail();` with no OwnerWriteGuard / whereHas-affiliate scoping.
Recommendation: Re-fetch through the same owner-scoped query as the resource (whereHas affiliate + OwnerQuery) or add OwnerWriteGuard support for signals; add a cross-owner regression test.
Confidence: high

3) severity: high | category: bug | file: src/Pages/PayoutBatchPage.php:159-179
Title: Manual "Reject" bypasses payout domain action and never releases reserved funds
Description: The reject action does `$payout->update(['status' => FailedPayout::class, ...])` directly instead of using UpdatePayoutStatus / PayoutReconciliationService. Unlike ProcessAffiliatePayout::recordResult (which calls releaseReservedFunds on failure), this path leaves funds reserved forever while reporting the payout as failed.
Evidence: `'status' => FailedPayout::class, 'metadata' => ...` direct update + `$payout->events()->create(...)` with no releaseReservedFunds call.
Recommendation: Route rejection through the domain action and call releaseReservedFunds (or move rejection into affiliates package as CONTEXT.md requires — no business rules in this adapter).
Confidence: high

4) severity: high | category: security | file: src/Pages/ManageAffiliateCommissionSettings.php:13-75
Title: Commission settings page has no authorization and no validation
Description: The page defines no canAccess()/authorization (unlike FraudReviewPage/PayoutBatchPage/ReportsPage), so any authenticated panel user who can reach the slug can read and overwrite global multi-level commission rates. save() casts unchecked input (`(float)($row['rate']??0)/100`) with no range/count validation, allowing negative/huge rates and unbounded level rows.
Evidence: class has mount/save/addLevel/removeLevel and getNavigationGroup/Sort but no canAccess; save() maps raw rows with no validator.
Recommendation: Add canAccess via FilamentPermission ability, validate rates (numeric, min 0, max sane bound) and cap level count.
Confidence: high

5) severity: medium | category: security | file: src/Pages/Portal/PortalRegistration.php:82-103,200-297
Title: Registration override: raw user mass-assignment + unauthenticated affiliate-code enumeration oracles
Description: (a) handleRegistration does `$userData=$data; unset(affiliate fields); ::create($userData)` trusting the Livewire data array (relies solely on model casts for password hashing — verify User has hashed cast). (b) checkCodeAvailability/checkReferralCode are public, unauthenticated, unthrottled Livewire actions returning existence of any affiliate code — an enumeration oracle. (c) Referral resolution via findByCode/findActiveAffiliateByCookie is not owner-scoped, allowing cross-owner upline grafting; the affiliate_code unique rule ignores owner scope.
Evidence: `$user = $this->getUserModel()::create($userData);` ; `Affiliate::query()->whereRaw('LOWER(code) = ?',...)->exists()` in two public methods; `app(AffiliateLookup::class)->findByCode($data['referral_code'])`.
Recommendation: Explicitly pick name/email/password (+Hash::make fallback), add rate limiting, scope lookup + uniqueness by owner.
Confidence: med

6) severity: medium | category: bug/security | file: src/Pages/Portal/PortalConversions.php:52-64
Title: orWhereIn without grouping leaks/overmatches conversions
Description: `$query->where('affiliate_id',$id); if(...){$query->orWhereIn(...)}` produces `A OR B`, so any subsequent AND constraints (Filament search/sort scopes) bind only to the second branch (`A OR (B AND C)`). Descendant ids are also fetched from AffiliateUpline without owner scoping.
Evidence: `->where('affiliate_id', $affiliateId); ... $query->orWhereIn('affiliate_id', $descendantIds);`
Recommendation: Single `whereIn('affiliate_id', [$own, ...$descendants])`; scope the upline lookup to the owner.
Confidence: high

7) severity: medium | category: security | file: src/Services/PayoutExportService.php:138-148,285-324,42-48
Title: Export has CSV formula injection, unescaped PDF/HTML meta block, unsanitized filename
Description: affiliate_code/external_reference are written raw to CSV/XLSX (cells starting with =,+,-,@ execute on open). buildPdfHtml interpolates `$payout->reference`, status and totals unescaped into title/meta (only table cells use htmlspecialchars). Filenames use raw reference in Content-Disposition.
Evidence: `getRowData` returns raw casts; `$csv->insertOne($this->getRowData(...))`; `<title>Payout Report - {$payout->reference}</title>`; `sprintf('%s.csv', $payout->reference)`.
Recommendation: Prefix/sanitize leading =,+,-,@ (e.g. prepend '), escape all meta interpolations, sanitize filename (allowlist [A-Za-z0-9-_]).
Confidence: high

8) severity: medium | category: bug | file: src/Actions/BulkPayoutAction.php:57-61
Title: Bulk payout always reports success even when every payout fails
Description: `$failed` is counted but never surfaced; `sendSuccessNotification()` runs unconditionally and `success()` only reflects processed>0, so an all-failed run still shows a success toast.
Evidence: `if ($processed > 0) { $this->success(); } $this->sendSuccessNotification();` with `$failed++` unused.
Recommendation: Branch notifications on processed/failed counts (success/partial/failure), matching PayoutBatchPage's counted message.
Confidence: high

9) severity: medium | category: security | file: src/Pages/Portal/PortalLinks.php:83-129
Title: generateLink trusts public Livewire property; host check is incomplete
Description: generateLink is a public action reading public $targetUrl with no validator call (the `->url()` rule exists only on the header-action form, bypassable). The guard compares only exact host: rejects valid subdomains/case variants, allows non-http(s) schemes to the same host, and builds generatedShortLink by naive path concatenation. Code is appended without urlencoding.
Evidence: `public string $targetUrl`; `$targetHost !== $allowedHost` strict compare; `$this->generatedLink = $this->targetUrl . ...`.
Recommendation: Validate targetUrl server-side (url rule + allowed schemes http/https + host allowlist incl. subdomains), urlencode code.
Confidence: high

10) severity: medium | category: performance | file: src/Widgets/UplineVisualizationWidget.php:99-160; src/Concerns/InteractsWithAffiliate.php:234-247; src/Pages/Portal/PortalDashboard.php:32-52
Title: Unbounded recursive/N+1 reads on dashboard and upline widget
Description: PortalDashboard::getViewData fires ~8 separate queries plus unbounded getDownlines()->get(); PortalSupport/PortalPrograms/PortalCreatives load all tickets/programs/creatives with per-row queries (getMembership + creatives per program, creatives per program). UplineVisualizationWidget::buildNode recurses with a query per node and unbounded breadth; public $depth is user-controllable (deep-tree DoS); calculateAverageChildren loads every affiliate-with-children into memory on each render.
Evidence: `->get()` with no limit in getDownlines; `->flatMap(fn...->creatives()->...->get())`; `if ($currentDepth < $this->depth)` with `public int $depth`; `->withCount('children')->get()` then `->avg(...)`.
Recommendation: Paginate/limit breadth, cap depth server-side (ignore/ clamp client value), aggregate averages in SQL, cache dashboard aggregates.
Confidence: high

11) severity: medium | category: performance | file: src/Pages/PayoutBatchPage.php:91-103; src/Services/PayoutExportService.php:31-49; src/Widgets/PerformanceOverviewWidget.php:26-97
Title: Per-row queries, full-collection exports, and aggressive widget polling
Description: PayoutBatchPage payout_method column queries payoutMethods per row (N+1, payee eager-loaded but methods not). CSV/XLSX/PDF exports load all conversions into memory then print (not true streaming) — OOM on large payouts. PerformanceOverviewWidget runs 7 aggregates every 30s, RealTimeActivityWidget polls every 10s, AffiliateStatsAggregator 8 queries per render — no caching.
Evidence: `getStateUsing(... $payee->payoutMethods()->...->first())`; `foreach ($payout->conversions ...)`; `protected ?string $pollingInterval = '30s'/'10s'`.
Recommendation: Eager-load default payout method (withAggregate), chunk/cursor exports with streamed CSV, cache widget aggregates with short TTL.
Confidence: high

12) severity: medium | category: bug | file: src/Resources/AffiliateConversionResource/Tables/AffiliateConversionsTable.php:132-148; src/Pages/Portal/PortalProfile.php:113-136
Title: Direct state mutation bypasses transitions; payout-method switch is non-atomic
Description: updateStatus assigns `$conversion->status = new $statusClass` + save, bypassing any transitionTo guards/auditing, and allows pending→paid / rejected→paid jumps (visibility only excludes the target state). PortalProfile flips is_default off then creates/updates in two queries with no transaction — concurrency can yield zero or multiple defaults.
Evidence: `$conversion->status = new $statusClass($conversion); ... return $conversion->save();`; `::where(...)->update(['is_default'=>false]); ... ::create([... 'is_default'=>true])`.
Recommendation: Use the domain transition action with allowed-transition validation; wrap default-switch in a transaction with a unique partial index on (affiliate_id) where is_default.
Confidence: med

13) severity: low | category: security | file: resources/views/pages/portal/links.blade.php:26,40,56; dashboard.blade.php:118,251; vouchers.blade.php:58
Title: Affiliate/voucher codes interpolated into JS single-quoted strings — potential XSS on non-alphaDash codes
Description: `x-on:click="navigator.clipboard.writeText('{{ $code }}')"` — Blade HTML-escapes quotes to &#039; but the browser HTML-decodes attribute values before JS parsing, so a code containing `'` breaks out of the JS string. Portal registration enforces alphaDash, but the admin AffiliateForm code field has no alphaDash rule, so such codes can exist. (support.blade.php correctly uses @js() — copy that pattern.)
Evidence: `writeText('{{ $affiliateCode }}')` etc. vs admin form code field with only required/maxLength/unique.
Recommendation: Use `@js($code)` in all handlers and add alphaDash validation to the admin code field.
Confidence: med

14) severity: low | category: bug | file: src/Resources/AffiliatePayoutResource.php:78-86; src/Resources/AffiliatePayoutResource/Pages/CreateAffiliatePayout.php:32-38; src/Widgets/PayoutQueueWidget.php:51-54; src/Widgets/RealTimeActivityWidget.php:56-64
Title: Unscoped/unbounded affiliate picker; unconditional OwnerWriteGuard; hardcoded divideBy:100
Description: Payout form loads ALL affiliates via unscoped pluck (memory + cross-owner options; create page revalidates but the picker still leaks). CreateAffiliatePayout calls OwnerWriteGuard unconditionally while every other call site gates on affiliates.owner.enabled — inconsistent, likely breaks when owner mode is off. Money columns hardcode divideBy:100, wrong for zero-decimal currencies (JPY etc.), inconsistent with InteractsWithAffiliate::formatAmount which handles them.
Evidence: `Affiliate::query()->orderBy('name')->pluck('name','id')->all()`; unconditional `OwnerWriteGuard::findOrFailForOwner`; `->money(..., divideBy: 100)`.
Recommendation: Scope + searchable-lazy options, gate the guard on config, use MoneyFormatter for money columns.
Confidence: med

15) severity: low | category: bug | file: src/Pages/ReportsPage.php:101-124; src/Pages/PayoutBatchPage.php:224-238
Title: Unvalidated custom dates can 500; batch header aggregates duplicate queries
Description: CarbonImmutable::parse($this->startDate/$endDate) on unvalidated Livewire input throws on malformed dates; no start<=end check; live regeneration on every change. getViewData runs count + sum + grouped queries separately (3 queries where 1-2 suffice).
Evidence: `'custom' => $this->startDate ? CarbonImmutable::parse($this->startDate) : ...`; three separate pending queries.
Recommendation: Validate date|before_or_equal:endDate, debounce regeneration, combine aggregates.
Confidence: high

POSITIVES (brief)
- Owner scoping is consistently applied in most admin resources (OwnerUiScope/forOwner) and payout write paths revalidate via OwnerWriteGuard; fraud-signal resource scoping via whereHas(affiliate) is the right pattern (actions just fail to reuse it).
- ProcessAffiliatePayout uses lockForUpdate + idempotent reconcile logic with lease expiry and reserved-funds release — solid double-processing protection.
- Most Blade output uses {{ }} escaping; support reply ticket lookup is correctly constrained to the caller's affiliate_id; PortalLinks has a same-host guard (just incomplete); money is int minor units throughout.
- No raw SQL injection (all whereRaw use bindings), no eval/unserialize/deserialization, no file upload/path traversal, no SSRF fetchers, no Octane-unsafe static mutable state (only a config cache on the panel provider), no FK violations (adapter has no migrations).

### Prior-audit findings (verbatim chunk)

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-affiliates` MEDIUM-HIGH — `AffiliatePayoutResource:80-83` unscoped affiliate options (enumeration; write re-validated). link/membership/ticket `relationship()` no `modifyQueryUsing`.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

### Verification verdicts

- [R1:#1] CONFIRMED high security | src/Resources/AffiliateResource/Schemas/AffiliateForm.php:196-197 | "Hidden owner_type/owner_id are" Hiddens lack dehydrated(false); arbitrary owner set via tampering
- [R1:#2] CONFIRMED high security | src/Actions/UpdateAffiliateFraudSignalStatus.php:27-29 | "Fraud-signal status change" unscoped re-fetch; policy update checks perms only, no owner check
- [R1:#3] CONFIRMED high bug | src/Pages/PayoutBatchPage.php:159-179 | "Manual Reject bypasses" direct FailedPayout update; no releaseReservedFunds, funds stuck reserved
- [R1:#4] CONFIRMED high security | src/Pages/ManageAffiliateCommissionSettings.php:13-75 | "Commission settings page" no canAccess (siblings have it); save() casts raw rates, no validation
- [R1:#5] DOWNGRADED (was medium) low security | src/Pages/Portal/PortalRegistration.php:82-103 | "Registration override: raw" form-scoped data; codes public by design; findByCode FALSE per FALSE-list
- [R1:#6] CONFIRMED med bug | src/Pages/Portal/PortalConversions.php:52-64 | "orWhereIn without grouping" A OR B breaks AND scopes; upline ids unscoped
- [R1:#7] CONFIRMED med security | src/Services/PayoutExportService.php:138-148 | "Export has CSV" raw cells (=,+,-,@), unescaped PDF meta/title, raw reference filename
- [R1:#8] CONFIRMED med bug | src/Actions/BulkPayoutAction.php:57-61 | "Bulk payout always" $failed counted, never surfaced; success toast unconditional
- [R1:#9] CONFIRMED med security | src/Pages/Portal/PortalLinks.php:83-129 | "generateLink trusts public" $targetUrl unvalidated; exact-host check; naive concat, no urlencode
- [R1:#10] CONFIRMED med performance | src/Widgets/UplineVisualizationWidget.php:99-160 | "Unbounded recursive/N+1 reads" per-node queries; public $depth unclamped; avg loads all
- [R1:#11] CONFIRMED med performance | src/Pages/PayoutBatchPage.php:91-103 | "Per-row queries, full-collection" N+1 methods; exports load all; 30s/10s polling uncached
- [R1:#12] CONFIRMED med bug | src/Resources/AffiliateConversionResource/Tables/AffiliateConversionsTable.php:132-148 | "Direct state mutation" bypasses allowTransition guards (pending→paid); payout switch non-atomic
- [R1:#13] CONFIRMED low security | resources/views/pages/portal/links.blade.php:26 | "codes interpolated into" writeText('{{ }}) breakout; admin code lacks alphaDash (AffiliateForm:38-53)
- [R1:#14] CONFIRMED low bug | src/Resources/AffiliatePayoutResource.php:78-86 | "Unscoped/unbounded affiliate picker" pluck-all DUP AUD:B1; uncond guard; divideBy:100 JPY wrong
- [R1:#15] CONFIRMED low bug | src/Pages/ReportsPage.php:101-124 | "Unvalidated custom dates" CarbonImmutable::parse throws; no start<=end; header runs 3 queries
- [AUD:B1] ADOPTED med-high bug | src/Resources/AffiliatePayoutResource.php:80-83 | DUP R1:#14 unscoped options verified; link/membership/ticket relationship() part plausible
- [AUD:B2] ADOPTED pass nav | src/Resources/AffiliatePayoutResource.php:1-30 | G1 Navigation PASS adopted; config-group pattern, no static group keys
- [AUD:B3] ADOPTED med performance | src/Resources/AffiliateFraudSignalResource.php:196-214 | G2 uncached badge COUNT verified (scoped per FALSE-list, uncached); cache it
- [AUD:B4] ADOPTED med performance | src/Pages/Portal/PortalConversions.php:75 | G3 relation-field N+1 plausible (affiliate.code w/o with()); package-wide pattern
- [AUD:B5] ADOPTED med performance | src/Resources/AffiliatePayoutResource.php:78-86 | G4 pluck-all+preload verified; DUP R1:#14; use relationship()+search
- [AUD:B6] ADOPTED low bug | src/Widgets/UplineVisualizationWidget.php:99-160 | G5 tree math in adapter verified (buildNode/avg); belongs in core
- [AUD:B7] ADOPTED med performance | src/Widgets/PerformanceOverviewWidget.php:26-97 | G6 uncached counts per render verified (7 aggregates, 30s poll, no cache)

---

## filament-authz

### E2E findings (verbatim)

End-to-end review of packages/filament-authz (50 files; no models/migrations/widgets/jobs in package; tests live in monorepo tests/src/FilamentAuthz*).

HIGH
1. [high/bug] src/Actions/ImpersonateAction.php:130-161 — Page action never redirects after take(). Evidence: `impersonate()` ends with `$manager->take(...)` and no redirect, while ImpersonateManager::take() rotates session id + CSRF token; the sibling ImpersonateTableAction redirects (line 190). Result: stale page, subsequent Livewire requests 419, silent failure (return value ignored). Recommend: mirror table action — check result, sanitize + redirect, notify on failure. Confidence high.

MEDIUM
2. [medium/bug] src/Middleware/SyncAuthzTenant.php:34-59 — No try/finally around team-context swap. Evidence: `setPermissionsTeamId($tenant->getKey())`, `$response = $next($request)`, restore after — an exception skips restore, leaking team id + flushed permission cache into the next Octane request. Recommend try/finally. Confidence high.
3. [medium/bug] src/Concerns/HasPanelAuthz.php:33 — Hardcoded `'panel.'.$panel->getId()` bypasses PermissionKeyBuilder (default camel/`.`, configurable case/separator). Hyphenated panel ids (`app-admin` → discovered `panel.appAdmin`) or a custom separator/case grant-then-deny access. Recommend resolving via FilamentAuthz/Authz key builder. Confidence high.
4. [medium/bug] src/Console/SeederCommand.php:213-281 — (a) Roles keyed by `$result[$role->name]`, silently dropping same-name roles across guards/scopes from the generated seeder. (b) Role/permission/guard names interpolated unescaped into single-quoted PHP (`'{$perm}'`, `'{$roleName}'`) — admin-created names containing quotes break or inject code into AuthzSeeder. Recommend keying by guard+scope+name and var_export(). Confidence high.
5. [medium/bug] src/FilamentAuthzPlugin.php:663-759 — applyConfigOverrides() writes per-panel fluent settings (exclusions, tabs, scopes.enforce) into global config at register() time, so in multi-panel apps the last-registered panel wins while Authz/EntityDiscoveryService read only global config. Recommend per-panel plugin-instance reads in discovery paths (the pattern PermissionTabFactory already uses for tabs). Confidence med-high.
6. [medium/security] src/Support/UserAuthzForm.php:73-89 — Direct-permissions saveRelationshipsUsing syncs submitted `$state` IDs with no existence/guard check, unlike the roles path which throws AuthorizationException on out-of-scope IDs (lines 225-227). Forged IDs attach cross-guard/dangling pivots. Recommend filtering state to valid permission IDs for the guard. Confidence med-high.
7. [medium/performance] src/Tables/Actions/ImpersonateTableAction.php:88-123 — canImpersonate() runs ImpersonationScopeGuard::canAccessTarget() (EXISTS over two pivot tables) + UserRoleChecker::hasGlobalRole() (role reload) per table row — ~50-75 extra queries per 25-row users page. Recommend memoizing actor authorization per request and deferring scope checks after cheap checks. Confidence high.
8. [medium/performance] src/Forms/Components/PermissionTabFactory.php:483-493 — setPermissionStateForRecord() runs `$record->permissions()->pluck('name')` inside afterStateHydrated of every checkbox section (each resource + pages + widgets + custom + panels + direct) — dozens of identical queries per role form. Recommend request-memoizing names per record key. Confidence high.

LOW
9. [low/bug] src/Console/GeneratePoliciesCommand.php:52-61 — No null check after `Filament::getPanel($panelId)`; invalid --panel causes TypeError in getTargetResources(Panel). DiscoverCommand (lines 53-57) handles this correctly. Recommend same guard. Confidence high.
10. [low/security] src/Resources/RoleResource/Schemas/RoleForm.php:69-75 — Central-app scope select has no in:/exists validation, so a forged scope UUID outside scope_options (or dangling) is mass-assigned via CreateRole. Recommend exists:authz_scopes,id + scope_options containment rule. Confidence med-high.
11. [low/security] src/Resources/UserResource.php:221-236 — Password field has no min-length/Password rule or confirmation. Recommend Password::defaults(). Confidence high.
12. [low/bug] src/Forms/Components/PermissionTabFactory.php:118,160,223,294 — Labels interpolated unescaped into visibleJs single-quoted strings (`'{$lowerLabel}'`); a model label with an apostrophe breaks the Alpine expression (sections mis-hide); translator-controlled string reaching JS. Recommend JS-escaping. Confidence med.
13. [low/security] src/Http/Controllers/ImpersonateController.php:19-71 — Actor authorization is checked after target lookup/scope checks, giving any authenticated user a 404-vs-403 user-existence oracle; mitigated by UUID PKs. Recommend authorizing the actor first. (Minor: Filament::auth() vs `auth`-middleware guard mismatch can 403 legit sessions.) Confidence high.
14. [low/performance] src/Resources/PermissionResource.php:248-259 + PermissionTabFactory.php:408-434 — renderDirectUsers() selects full user rows (password hashes in memory, only name/email shown); getDirectPermissionOptions() plucks all permission names unbounded. Recommend column selection. Confidence high.
15. [low/bug] src/Console/GeneratePoliciesCommand.php:182-224 — Permission names interpolated unescaped into generated `$user->can('...')` strings. Low risk (discovery-derived), same var_export() fix. Confidence med.

POSITIVES (verified): tenant/scope enforcement on UserResource (ImpersonationScopeGuard) and RoleResource (applyTenantScope + central_app scope limit) queries, so Filament record bindings are scoped; role-sync server-side scope revalidation; redirect allowlist + same-host backTo sanitizers at controller, action, manager, and leave-controller layers; impersonation guards (self/already/canBeImpersonated/super-admin default); banner output fully escaped; Octane-safe (scoped Authz/plugin bindings, flush listeners, stateless singleton discovery); bounded pagination; navigation via config group + getNavigationGroup on all three resources; no FK/SoftDeletes/money/index issues (no migrations); optional affiliates dependency correctly guarded by class_exists. No SSRF/path-traversal/deserialization/XSS vectors found in web-facing code.

### Prior-audit findings (verbatim chunk)

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-authz` LOW/MEDIUM — `PermissionResource` bare (OK if global); `UserResource:47-50` only impersonation guard, no `OwnerUiScope` (confirm User ownership); impersonate POST-only + 403 GOOD.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

### Verification verdicts

- [R1:#1] CONFIRMED high bug | src/Actions/ImpersonateAction.php:130-161 | "Page action never" ends at take(), no redirect; sibling table action redirects (:190); 419s
- [R1:#2] CONFIRMED med bug | src/Middleware/SyncAuthzTenant.php:34-59 | "No try/finally around" exception skips team-id restore; Octane leak
- [R1:#3] CONFIRMED med bug | src/Concerns/HasPanelAuthz.php:33 | "Hardcoded panel prefix bypasses" PermissionKeyBuilder; hyphen ids/custom sep break
- [R1:#4] CONFIRMED med bug | src/Console/SeederCommand.php:213-281 | "Roles keyed by" name-only drops cross-guard dupes; unescaped interpolation into seeder PHP
- [R1:#5] CONFIRMED med bug | src/FilamentAuthzPlugin.php:663-759 | "applyConfigOverrides() writes per-panel" fluent settings to global config; last panel wins
- [R1:#6] CONFIRMED med security | src/Support/UserAuthzForm.php:73-89 | "Direct-permissions saveRelationshipsUsing syncs" raw $state IDs; roles path throws (225-227)
- [R1:#7] CONFIRMED med performance | src/Tables/Actions/ImpersonateTableAction.php:88-123 | "canImpersonate() runs ImpersonationScopeGuard" + role reload per row; ~2-3 queries/row
- [R1:#8] CONFIRMED med performance | src/Forms/Components/PermissionTabFactory.php:483-493 | "setPermissionStateForRecord() runs permissions()" pluck per checkbox section; memoize
- [R1:#9] CONFIRMED low bug | src/Console/GeneratePoliciesCommand.php:52-61 | "No null check" after getPanel; invalid --panel TypeErrors
- [R1:#10] CONFIRMED low security | src/Resources/RoleResource/Schemas/RoleForm.php:69-75 | "Central-app scope select" nullable, no in:/exists rule; forged scope mass-assigned
- [R1:#11] CONFIRMED low security | src/Resources/UserResource.php:221-236 | "Password field has" no min/Password rule or confirmation
- [R1:#12] CONFIRMED low bug | src/Forms/Components/PermissionTabFactory.php:118 | "Labels interpolated unescaped" into visibleJs quotes (:118,:160); apostrophe breaks Alpine
- [R1:#13] CONFIRMED low security | src/Http/Controllers/ImpersonateController.php:19-71 | "Actor authorization is" after target lookup (404-vs-403 oracle); UUIDs mitigate
- [R1:#14] CONFIRMED low performance | src/Resources/PermissionResource.php:248-259 | "renderDirectUsers() selects full" rows incl hashes; options pluck unbounded
- [R1:#15] CONFIRMED low bug | src/Console/GeneratePoliciesCommand.php:182-224 | "Permission names interpolated" into can('...'); discovery-derived; var_export fix
- [AUD:B1] ADOPTED low security | src/Resources/UserResource.php:47-50 | Only impersonation guard verified, no OwnerUiScope; PermissionResource bare OK-if-global
- [AUD:B2] ADOPTED low bug | src/Resources/RoleResource.php:149-162 | G1 badge delegation via getPlugin() verified; rest PASS
- [AUD:B3] ADOPTED med performance | src/Resources/RoleResource.php:149-153 | G2 badge here has no COUNT (config/plugin); uncached-count pattern plausible
- [AUD:B4] ADOPTED med performance | src/Resources/UserResource.php:47-50 | G3 N+1 pattern plausible; package-specific instances not enumerated
- [AUD:B5] ADOPTED pass performance | src/Support/UserAuthzForm.php:66-71 | G4 GOOD cited here verified (modifyQueryUsing); nothing to fix
- [AUD:B6] ADOPTED pass bug | src/Services/EntityDiscoveryService.php:22 | G5 no authz leakage cited; discovery via key builder; plausible PASS
- [AUD:B7] ADOPTED med performance | src/Forms/Components/PermissionTabFactory.php:408-434 | G6 uncached plucks analogue verified (direct users + permission options)

---

## filament-cart

### E2E findings (verbatim)

End-to-end review of packages/filament-cart (adapter-only Filament v5 UI over cart snapshots/conditions; no models/migrations/routes in package).

FINDINGS

1) severity: high | category: bug | packages/filament-cart/src/Resources/CartResource/RelationManagers/ConditionsRelationManager.php:19
Title: Relation manager renders snapshot conditions with the stored-Condition table config
Description: Relationship `cartConditions` returns `CartSnapshotCondition` rows, but the table is `ConditionsTable::configure()`, built for stored `Condition`. `CartSnapshotCondition` has no `display_name` or `is_active` columns, so those searchable/sortable columns render empty and throw SQL errors on sort/search. `recordActions()` (verified alias that resets) replaces the row Edit/Delete actions, but `bulkActions(deleteSelected)` is inherited and calls `ConditionResource::canDelete()` → `isGlobalRecordOutsideExplicitGlobalContext(Condition $record)` (typed param) plus `authorizeCondition(Condition ...)`, so bulk-deleting in this tab fatals with TypeError on `CartSnapshotCondition` records.
Evidence: `protected static string $relationship = 'cartConditions'; ... return ConditionsTable::configure($table)->headerActions([...])->recordActions([RemoveConditionAction::make()]);` vs `ConditionsTable` columns `display_name`, `is_active` and bulk action calling typed `Condition` helpers.
Recommendation: Build a dedicated snapshot-conditions table (columns limited to fields on `CartSnapshotCondition`: name/type/target/value/order/operator/flags/parsed_value) with only `RemoveConditionAction` row/bulk actions; drop the inherited stored-condition bulk delete.
Confidence: high

2) severity: high | category: bug | packages/filament-cart/src/Listeners/SendCartAbandonedNotification.php:9,28
Title: Hard dependency on aiarmada/checkout which is not in composer require
Description: The listener references `AIArmada\Checkout\Models\CheckoutSession::query()` directly, but composer.json requires only commerce-support, cart, filament. In this monorepo checkout is present so it works, but a standalone install fatals (uncatchable `Error`) whenever `CartAbandoned` fires, since the provider always registers the listener. This also violates the package's "adapter only" boundary by encoding checkout-session recovery logic.
Evidence: `use AIArmada\Checkout\Models\CheckoutSession; ... CheckoutSession::query()->where('cart_id', ...)` with no `class_exists` guard and no `aiarmada/checkout` in require.
Recommendation: Either add the dependency, or guard with `class_exists` + config flag and return early, or move recovery lookup behind an optional contract/event in cart/checkout.
Confidence: high

3) severity: medium | category: security | packages/filament-cart/src/Listeners/SendCartAbandonedNotification.php:62-69 + src/Notifications/CartAbandonedNotification.php:45
Title: Unvalidated retry URL and subject inputs in abandonment email
Description: `payment_redirect_url` (stored per-session, gateway-influenced) is used verbatim as the mail button `:url`, enabling an open-redirect/phishing link if the value is ever attacker-influenced; fallback `config('app.url')` is fine. Separately, `offer_name` (cart item name, merchant/user input) is interpolated into the mail subject without stripping CR/LF.
Evidence: `if (is_string($session->payment_redirect_url) ...) return $session->payment_redirect_url;` → `<x-mail::button :url="$retryUrl">`; `->subject(sprintf('Your %s checkout ...', $offerName))`.
Recommendation: Accept only http(s) URLs whose host is in an allowlist (else fall back to a signed in-app recovery route); strip `\r\n` from subject parts; also validate scheme before rendering the button.
Confidence: med

4) severity: medium | category: bug | packages/filament-cart/src/Listeners/SendCartAbandonedNotification.php:39-44
Title: Purchaser email used without format validation
Description: `billing_data['email']` is only checked `is_string` non-empty, then passed to `Notification::route('mail', ...)`. A malformed address fails on the queue worker with retries instead of failing fast/skipping. Listener is also synchronous, adding 2 queries + dispatch to the abandonment request path.
Evidence: `$purchaserEmail = $billingData['email'] ?? null; if (! is_string(...) ...) return; Notification::route('mail', $purchaserEmail)->notify(...)`.
Recommendation: `filter_var($purchaserEmail, FILTER_VALIDATE_EMAIL)` guard; consider ShouldQueue on the listener.
Confidence: high

5) severity: medium | category: performance | packages/filament-cart/src/Pages/CartDashboard.php:46-66
Title: Abandoned-cart count query runs twice per navigation render
Description: `getNavigationBadge()` and `getNavigationBadgeColor()` each call `getAbandonedCartCount()`, doubling an identical filtered count on every page load.
Evidence: Both methods call `self::getAbandonedCartCount()` with no memoization.
Recommendation: Memoize per-request (static local or `OwnerCache::remember` short TTL).
Confidence: high

6) severity: medium | category: performance | packages/filament-cart/src/Resources/CartResource/Tables/CartsTable.php:207-232
Title: Bulk clear/delete loops N authorize+resolve+sync cycles with no chunking or transaction
Description: Each selected cart triggers `authorizeCart` (extra SELECT) + `resolveForSnapshot` + full `clear()/destroy()`+sync; a mid-loop exception aborts leaving a partially processed selection with no per-row error reporting.
Evidence: `$records->each(function (Cart $record) { $cart = self::authorizeCart($record); app(CartInstanceManager::class)->resolveForSnapshot($cart)->clear(); })`.
Recommendation: Chunk with a per-row try/catch summary notification, or dispatch a queued bulk job; wrap each row idempotently.
Confidence: high

7) severity: medium | category: performance | packages/filament-cart/src/Resources/CartItemResource/Tables/CartItemsTable.php:36,46 + src/Resources/CartItemResource.php:58-64
Title: N+1 parent cart load in items table money formatting
Description: Every row calls `$record->cart->currency` in `formatStateUsing`, and `getEloquentQuery()` never eager-loads `cart`, so each page costs one extra query per row.
Evidence: `fn ($state, $record) => self::formatMoney(..., $record->cart->currency ?? null)`; query only adds `whereIn('cart_id', ...)` subquery.
Recommendation: Add `->with('cart')` (or `->eagerLoadRelations()`) in `CartItemResource::getEloquentQuery()` / relation manager query.
Confidence: high

8) severity: medium | category: bug | packages/filament-cart/src/Widgets/CartStatsWidget.php:48,78
Title: Total Value stat sums minor units across mixed currencies
Description: `sum('total')` aggregates all owner carts regardless of currency, then formats with the default currency — misleading whenever more than one currency exists.
Evidence: `$totalValue = (int) (clone $base)->where('items_count','>',0)->sum('total'); ... Stat::make('Total Value', $this->formatMoney($totalValue))` where `formatMoney` uses `CartMoney::formatMinor($amount)` default currency.
Recommendation: Group by currency (one stat per currency or dominant-currency + count), or scope the widget to a single currency filter.
Confidence: high

9) severity: medium | category: bug | packages/filament-cart/src/Resources/CartResource/Schemas/CartForm.php:28-31,76-80,129-132 + Pages/CreateCart.php + Pages/EditCart.php
Title: Cart form validation contradicts DB contract; create/edit pages are dead code
Description: `identifier ->unique()` is global, but the core migration uniques `(owner_scope, identifier, instance)` — cross-owner false collisions. Item `price` is `numeric()` with no min, allowing negatives; condition `value` is `numeric()`, rejecting the `%` syntax the core supports. `CreateCart`/`EditCart` exist with save paths that never assign an owner, but `CartResource::getPages()` registers only index/view with `canCreate/canEdit=false`, so they are unreachable dead code that will misbehave if ever wired up.
Evidence: `TextInput::make('identifier')->required()->unique(ignoreRecord: true)`; `TextInput::make('value')->numeric()->required()`; `getPages()` returns only index+view.
Recommendation: Scope the unique rule by owner_scope+instance (mirroring the migration), add `minValue(0)` on price, accept `%` values or drop the repeater, and either delete the dead pages or give them owner assignment + write guards before registering.
Confidence: high

10) severity: low | category: bug | packages/filament-cart/src/Widgets/RecentActivityWidget.php:67-89
Title: limit(50) fights pagination; selectRaw drops owner attributes from hydration
Description: `limit(50)` on a `paginated([10])` table silently caps the dataset at 50 rows with confusing paginator totals. The raw select also omits owner columns, so hydrated rows lack owner attributes for any downstream policy/display use (the `forOwner` WHERE itself still applies).
Evidence: `->selectRaw("id, identifier as session_id, ...")->orderByDesc(...)->limit(50); $query->forOwner(...)`.
Recommendation: Remove `limit(50)` (rely on pagination) or convert to a non-paginated `limit(50)->get()` list; include owner columns in the select.
Confidence: med

11) severity: low | category: bug | packages/filament-cart/src/Actions/ApplyConditionAction.php:53,106,274 + src/Actions/RemoveConditionAction.php:75,124
Title: Cart resolution throws outside try/catch → 500 instead of Filament error
Description: `resolveCartRecord()` (throws `InvalidArgumentException` / 404 from `OwnerWriteGuard`) is called before the `try` in all apply/clear paths; e.g. an orphan item (`$record->cart === null`) in `makeForItem` throws uncaught.
Evidence: `$cart = self::resolveCartRecord($record, $livewire); try { ... } catch ...`.
Recommendation: Move resolution inside the `try` (or wrap with a danger notification + return).
Confidence: high

12) severity: low | category: performance | packages/filament-cart/src/Resources/CartItemResource.php:81-84 + src/Resources/CartResource.php:87-92 + src/Resources/ConditionResource.php:98-103
Title: Navigation badges run uncached counts on every render; items badge shows "0"
Description: Three resources + dashboard each issue count queries per request with no cache. `CartItemResource` additionally casts unconditionally to string, rendering a "0" badge instead of hiding.
Evidence: `return (string) self::getEloquentQuery()->count();` vs siblings returning `null` when 0.
Recommendation: Return `null` on 0 and cache/memoize badge counts (e.g. `OwnerCache::remember`, 60s like `CartStatsWidget`).
Confidence: high

13) severity: low | category: performance | packages/filament-cart/src/Actions/ApplyConditionAction.php:131-142
Title: Condition options load entire active catalog into the modal on every open
Description: `getConditionOptions()` does an unbounded `->get()` + groupBy per modal render; fine for dozens of conditions, heavy for large catalogs.
Evidence: `$query->orderBy('type')->orderBy('name'); return $query->get()->groupBy('type')->...`.
Recommendation: Use a searchable relationship-driven select with limit, or cap + `getSearchResultsUsing`.
Confidence: med

14) severity: low | category: bug | packages/filament-cart/src/Resources/ConditionResource/Tables/ConditionsTable.php:53-60
Title: Target column match arms never match real stored targets
Description: The formatter matches `'subtotal'/'total'/'item'`, but stored targets are `'cart@cart_subtotal/aggregate'` etc., so the raw long string is always shown via `ucfirst` default.
Evidence: `match ($state) { 'subtotal' => ..., 'total' => ..., 'item' => ..., default => ucfirst($state) }`.
Recommendation: Map the real `cart@.../items@...` values (same labels as the form/filter).
Confidence: high

15) severity: low | category: bug | packages/filament-cart/src/Services/CartDownloadService.php:18-24,30-43
Title: Silent JSON-encode fallback; export relies solely on caller for authorization
Description: `json_encode(...) ?: '{}'` masks encoding failures with an empty-looking export. Payload includes full items/conditions/metadata (PII); the service itself performs no owner check — currently safe only because `ViewCart::export_cart` authorizes first.
Evidence: `echo json_encode($payload, ...) ?: '{}';` with no `JSON_THROW_ON_ERROR`; no guard in `download()/payload()`.
Recommendation: Throw on encode failure; document caller-must-authorize (or accept an already-authorized snapshot type / assert inside).
Confidence: med

16) severity: low | category: performance | packages/filament-cart/src/Resources/ConditionResource/Pages/EditCondition.php:31-48
Title: "Remove from All Carts" runs synchronously in the request
Description: `RemoveStoredConditions::handle()` fans out across all matching carts inline; large fleets will hit request timeouts with no progress feedback.
Evidence: Header action calls `->handle($record)` directly and reports counts in a notification.
Recommendation: Dispatch a queued job and notify on completion.
Confidence: med

AUTH NOTE (low/info): `LiveDashboardPage::canAccess()` checks only config, and there are no policies — any authenticated panel user can view dashboards and (via tables/actions) clear/delete carts and apply conditions. Filament v5 `EditRecord::authorizeAccess()` does honor `ConditionResource::canEdit()` (verified in vendor), so the global-record edit block holds including direct URLs. Consider panel-role checks if non-admin staff get panel access.

POSITIVES (brief): Owner scoping is consistently correct — all three resources scope reads (`forOwner` / cart_id subquery for items), every write path revalidates via `OwnerWriteGuard`, and core `ApplyStoredCondition`/`RemoveStoredConditions` re-authorize again defense-in-depth; global-condition edit/delete gates are enforced on pages too. No SQL injection (raw fragments are static; `ILIKE ?` and LIKE values are bound). Money stays int minor units via `CartMoney`. Filament navigation uses `config(navigation.group)` + `getNavigationGroup` per repo rules. Download filename is traversal-sanitized; Blade output is escaped (`{{ }}`); no `unserialize`, SSRF sinks, FK violations (no migrations), SoftDeletes, or Octane-unsafe static state; singletons are stateless. Core migrations index every column this package filters/sorts (abandoned/started/activity/identifier/instance/totals). Pagination is bounded; stats widget caches via owner-scoped `OwnerCache`. Test coverage exists under tests/src/FilamentCart (actions scoping, download service, widgets, notification listener).

### Prior-audit findings (verbatim chunk)

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-cart` — scoping exemplary. No badge `"0"` issue (resources return `null` when 0).
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

### Verification verdicts

- [R1:#1] CONFIRMED high bug | src/Resources/CartResource/RelationManagers/ConditionsRelationManager.php:19 | "Relation manager renders" snapshot rows with stored table; display_name/is_active absent; bulk TypeError
- [R1:#2] CONFIRMED high bug | src/Listeners/SendCartAbandonedNotification.php:9 | "Hard dependency on" aiarmada/checkout absent from require (suggest only); standalone fatal
- [R1:#3] CONFIRMED med security | src/Listeners/SendCartAbandonedNotification.php:62-69 | "Unvalidated retry URL" verbatim mail button (open redirect); offer_name CR/LF in subject
- [R1:#4] CONFIRMED med bug | src/Listeners/SendCartAbandonedNotification.php:39-44 | "Purchaser email used" is_string-only; malformed fails on queue worker; listener sync
- [R1:#5] CONFIRMED med performance | src/Pages/CartDashboard.php:46-66 | "Abandoned-cart count query" badge+color each call count(); DUP AUD:B3 (audit worst-case)
- [R1:#6] CONFIRMED med performance | src/Resources/CartResource/Tables/CartsTable.php:207-232 | "Bulk clear/delete loops" authorize+resolve+sync per row; mid-loop abort partial
- [R1:#7] CONFIRMED med performance | src/Resources/CartItemResource/Tables/CartItemsTable.php:36 | "N+1 parent cart" $record->cart per row; getEloquentQuery lacks with('cart')
- [R1:#8] CONFIRMED med bug | src/Widgets/CartStatsWidget.php:48 | "Total Value stat" sums minor units across currencies; formatted in default currency
- [R1:#9] CONFIRMED med bug | src/Resources/CartResource/Schemas/CartForm.php:28-31 | "Cart form validation" global unique vs scoped migration; price no min; value rejects %; dead pages
- [R1:#10] CONFIRMED low bug | src/Widgets/RecentActivityWidget.php:67-89 | "limit(50) fights pagination" caps paginated table; select omits owner cols
- [R1:#11] CONFIRMED low bug | src/Actions/ApplyConditionAction.php:53 | "Cart resolution throws" before try (:53,:106; Remove :75,:124); orphan item 500s
- [R1:#12] CONFIRMED med performance | src/Resources/CartItemResource.php:81-84 | "Navigation badges run" uncached counts; (string) cast shows 0; DUP AUD:B3; FALSE-list stale
- [R1:#13] CONFIRMED low performance | src/Actions/ApplyConditionAction.php:131-142 | "Condition options load" unbounded get()+groupBy per modal open
- [R1:#14] CONFIRMED low bug | src/Resources/ConditionResource/Tables/ConditionsTable.php:52-60 | "Target column match" arms never hit; stored DSL is scope@phase/application
- [R1:#15] CONFIRMED low bug | src/Services/CartDownloadService.php:18-24 | "Silent JSON-encode fallback" ?: '{}' masks failure; no owner check in service
- [R1:#16] CONFIRMED low performance | src/Resources/ConditionResource/Pages/EditCondition.php:31-48 | "Remove from All" runs RemoveStoredConditions sync in request
- [R1:#17] CONFIRMED low security | src/Pages/LiveDashboardPage.php:34-37 | "LiveDashboardPage::canAccess() checks only" config; no policies; any panel user views/mutates
- [AUD:B1] ADOPTED low bug | src/Resources/CartItemResource.php:81-84 | Scoping exemplary adopted; badge-0 claim CONTRADICTED by :83; see R1:#12
- [AUD:B2] ADOPTED pass nav | src/Resources/CartResource.php:99-105 | G1 Navigation PASS adopted; config group + getNavigationGroup pattern
- [AUD:B3] ADOPTED med performance | src/Pages/CartDashboard.php:46-66 | G2 uncached COUNT verified; DUP R1:#5 + R1:#12 (counted once)
- [AUD:B4] ADOPTED med performance | src/Resources/CartItemResource/Tables/CartItemsTable.php:23-26 | G3 cart.identifier w/o with() verified; DUP R1:#7
- [AUD:B5] ADOPTED med performance | src/Actions/ApplyConditionAction.php:131-142 | G4 unbounded options analogue verified; DUP R1:#13
- [AUD:B6] ADOPTED pass bug | src/Widgets/CartStatsWidget.php:23-31 | G5 no cart leakage cited; stats via CartMoney/OwnerCache; plausible PASS
- [AUD:B7] ADOPTED med performance | src/Widgets/CartStatsWidget.php:43-60 | G6 SQL sums used (cached 60s); chart loop 7 counts analogue; plausible

---

## filament-cashier-chip

### E2E findings (verbatim)

End-to-end review: packages/filament-cashier-chip (adapter-only Filament UI over cashier-chip; no migrations/routes/jobs/commands/tests in package)

FINDINGS

1. CRITICAL / bug — src/Resources/SubscriptionResource/Pages/ListSubscriptions.php:36-60 — Bulk pause/resume mass-updates `chip_status` via raw query, bypassing domain transitions and owner scoping.
Evidence: `Subscription::query()->where('chip_status', Active)->update(['chip_status' => Paused->value])`. No `paused_at`, no model events, no CHIP sync (domain `pause()/resume()` at cashier-chip Subscription.php:1242,1257 do this). OwnerScope::apply no-ops when owner disabled (the cashier-chip default, `enabled=false`), so this rewrites EVERY owner's subscriptions cross-tenant. Query-builder `update()` also skips model events/observers.
Recommendation: remove these actions or reimplement as owner-scoped iteration calling `$sub->pause()/$sub->resume()` (or a cashier-chip Action), chunked. Confidence: high.

2. HIGH / bug — src/Resources/BaseCashierChipResource.php:47-86 + src/Resources/CustomerResource.php:41-44 — CustomerResource SQL-crashes when owner scoping is enabled; InvoiceResource checks the wrong owner key.
Evidence: base `getEloquentQuery()` unconditionally constrains `owner_type/owner_id` (via `OwnerQuery`, which always references those columns, OwnerQuery.php:43-60). `Cashier::$customerModel` defaults to base `Model::class` and is typically User/Team with NO owner columns and no `ownerScopeConfig()` → `SQLSTATE unknown column owner_type` as soon as `cashier-chip.features.owner.enabled=true`. Separately, `Purchase` (via ChipModel) is governed by `chip.owner` key, but InvoiceResource's gate reads `cashier-chip.features.owner.enabled` → scoping mismatches the model's own config.
Recommendation: override `getEloquentQuery()` in CustomerResource (billable-aware or explicit opt-out with documented rationale); gate each resource on its model's own `ownerScopeConfig()->enabled`. Confidence: high.

3. HIGH / bug — src/CustomerPortal/Pages/Subscriptions.php:156-187 + resources/views/pages/subscriptions.blade.php — Canceled grace-period subscriptions are invisible; Resume is unreachable.
Evidence: main list `whereIn(chip_status, [active,trialing,past_due])` excludes canceled; `getCancelledSubscriptions()` (`onGracePeriod()`) is fetched with `items+billable` but the blade only loops `$subscriptions` — `$cancelledSubscriptions` is never rendered. A canceled-but-gracing user sees "no active subscriptions" and can never resume.
Recommendation: render a second "Ending soon / Resume" section from `cancelledSubscriptions`. Confidence: high.

4. HIGH / performance — src/Resources/CustomerResource/Tables/CustomerTable.php:62-98,212-248 — Severe per-row N+1: `subscriptions_count` runs a `count()` per row (74-89); `defaultPaymentMethod()` is invoked twice per row (label + lastFour); plus `chipId()` and `onTrial()` per row. A 25-row page fires ~75-125 queries.
Recommendation: `withCount`/select-subselect for counts, compute payment-method label+lastFour once per record (memoize per row or eager-load `storedPaymentMethods`). Confidence: high.

5. HIGH / performance — src/Widgets/MRRWidget.php:119-149, RevenueChartWidget.php:79-131 (also Churn/Trial/Distribution/Attention widgets) — Dashboard fires ~60+ queries per load with full-collection hydration and no caching.
Evidence: MRR chart loop does 6× `->withSum(...)->get()->sum(closure)`; Revenue does 24× (12 MRR + 12 new-revenue) full hydrations; TrialConversions ~14 counts; Distribution 6 counts; Attention 5 counts. Re-run on 45s table / 120s chart polling per viewer.
Recommendation: compute SUM/COUNT in SQL (single grouped query per widget), cache stats with short TTL (60-300s) keyed by owner. Confidence: high.

6. MEDIUM / bug+performance(security-adjacent: DoS/timeout) — src/Resources/CustomerResource/Pages/ListCustomers.php:34-60 — "Sync All to Chip" is unscoped, unbounded, and synchronous.
Evidence: `$model::query()->whereDoesntHave('chipCustomerLink')->get()` (no owner scope, no chunking) then one CHIP API call per customer inside a web request; `whereDoesntHave('chipCustomerLink')` throws outside the try/catch if the customer model lacks the Billable relation; per-item exceptions swallowed into a counter.
Recommendation: move to a queued chunked job with owner scope and a `method_exists`/relation guard; surface failures. Confidence: high.

7. MEDIUM / bug — src/Concerns/InteractsWithCashierChipData.php:31 + all Widgets — Under explicit-global admin context every widget shows global-only (usually empty) data instead of aggregates.
Evidence: `OwnerUiScope::apply($query, includeGlobal: false)`; with null owner `OwnerQuery` returns `whereNull(owner_type)->whereNull(owner_id)` (OwnerQuery.php:46-48), so admin dashboards (BillingDashboard is also registered on the admin panel, FilamentCashierChipPlugin.php:170-172) render misleading zeros.
Recommendation: give admin-registered widgets an aggregate path or gate them (`canView`) to owner contexts. Confidence: medium (depends on host panel's OwnerContext setup).

8. MEDIUM / bug — src/Concerns/InteractsWithCashierChipData.php:42-53 — `normalizeToMonthly()` DivisionByZeroError when `billing_interval_count=0`.
Evidence: `match` arms `30/$count`, `1/$count`, `1/(12*$count)` — int/int division by zero throws. Domain guards this (`Subscription.php:835` uses `> 0 ? : 1`); the widget copy does not.
Recommendation: `$count = max(1, $count)`; better, move normalization into cashier-chip per the adapter-only guardrail. Confidence: high.

9. MEDIUM / bug — src/Widgets/ChurnRateWidget.php:39-88,132-163 — Churn denominators inconsistent: current-month filters Active/Trialing, previous-month and chart count ALL statuses → bogus trend/chart.
Recommendation: apply the same status filter in all three paths. Confidence: high.

10. MEDIUM / bug — src/Widgets/MRRWidget.php:41-76 — MRR discount handling inconsistent + business logic in adapter.
Evidence: current MRR subtracts full `coupon_discount` from a monthly-normalized amount (wrong for yearly plans); previous MRR ignores discounts entirely. Guardrail says calculations belong in cashier-chip.
Recommendation: move MRR computation to cashier-chip with interval-aware discount handling. Confidence: high (inconsistency), medium (materiality).

11. MEDIUM / security — CustomerPortal/Pages/Subscriptions.php:96-102,137-143; PaymentMethods.php:125-131,163-169 — Raw `$e->getMessage()` shown to end users (CHIP/API internals leak).
Recommendation: generic user message + `report($e)`/log. Confidence: high.

12. MEDIUM / bug — CustomerResource/RelationManagers/SubscriptionsRelationManager.php:21 vs CustomerResource.php:94-103 — hardcoded `$relationship='subscriptions'` but resource admits chipSubscriptions-only models → Filament "relationship not found" crash.
Recommendation: make the relationship name dynamic or only register when `subscriptions` exists. Confidence: high.

13. MEDIUM / bug — SubscriptionResource/Schemas/SubscriptionInfolist.php:91 — `method_exists($record->customer, 'chipId')` TypeErrors when `customer` is null (`method_exists()` requires object|string).
Recommendation: null-check first. Confidence: high.

14. MEDIUM / bug — CustomerPortal/Pages/PaymentMethods.php:65-94 — `getAddPaymentMethodUrl()` creates a CHIP setup purchase during page render (side-effect GET; `setupPaymentMethodUrl→createSetupPurchase` calls `createPurchase`), and the catch-all silently returns `'#'` with no feedback.
Recommendation: create the setup purchase lazily in a redirect action on click; notify on failure. Confidence: medium-high.

15. MEDIUM / performance — CustomerPortal/Pages/Invoices.php:105-114 — Unbounded invoice list: loads all invoices (domain also `loadMissing('subscriptions.items'...)`) with in-memory sort and renders every row, no pagination.
Recommendation: paginate or cap + lazy-load. Confidence: high.

16. LOW-MEDIUM / performance+bug — BaseCashierChipResource.php:32-37 — `getNavigationBadge()` runs a scoped `count()` per resource on every request AND asserts owner context (throws where no owner/global context exists).
Recommendation: guard with try/catch or `OwnerUiScope::canCreate`-style check; cache briefly. Confidence: high.

17. LOW / security — Widgets/RevenueChartWidget.php:69 — config `currency` interpolated unescaped into a JS callback string (`'{$currency} '`).
Recommendation: escape/allowlist (config-controlled, so low). Confidence: high.

18. LOW / bug — Dead buttons: InvoiceTable.php:160-168 `download_pdf` action body is empty; ListInvoices `export_csv` is a no-op. Both render clickable actions.
Recommendation: remove or implement. Confidence: high.

19. LOW / bug — BaseCashierChipResource.php:51 default `true` vs cashier-chip.php:43 default `false` for `owner.enabled` — divergent fail-open/fail-closed defaults if key is missing.
Recommendation: align default to `false` (match domain). Confidence: high.

20. LOW / bug — SubscriptionsRelationManager.php:112 `getStatusColor(SubscriptionStatus $status)` non-nullable while FormatsSubscriptionStatus accepts null; null `chip_status` → TypeError. Confidence: medium (DB likely non-null).

21. LOW / bug — CustomerTable.php:27,279-295 static `$genericTrialQuerySupport` schema cache persists across Octane requests/tests.
Recommendation: key already by connection|table; acceptable — note for Octane reset/test isolation. Confidence: medium.

22. LOW / bug — SubscriptionItemsRelationManager.php:99-148 quantity/price forms: `quantity`/`unit_amount` have min but no max; `price` is free text with no length/format check (admin-only).
Recommendation: add `maxValue`/`maxLength` + domain-side validation. Confidence: high.

23. LOW / bug — Invoices.php:59-77 `abort(404)` inside a Livewire action; prefer a Notification + early return. Confidence: medium.

POSITIVES (verified)
- Widgets scope via `OwnerUiScope::apply(..., includeGlobal:false)` (fail-closed); base resource asserts owner-or-explicit-global before querying.
- Portal cancel/resume resolve via `$billable->subscriptions()->find($id)` — billable-scoped, no IDOR.
- Domain `findInvoice()` verifies purchase client-id matches billable chip id; `PaymentMethodStore::setDefault/deleteForBillable` scope by billable + owner with `AuthorizationException` — portal-submitted IDs are revalidated server-side.
- Money consistently int minor units via `MoneyFormatter`; no migrations/FKs (correct for adapter); no SoftDeletes; navigation via `config(navigation.group)` + `getNavigationGroup()` per repo rules.
- Blades use escaped `{{ }}`; driver-specific raw SQL in InvoiceTable high-value filter uses bound parameters; setup purchases require `idempotency_key`.
- No mass-assignment surface (no models/forms binding), no `unserialize`/file-path input/deserialization, no SSRF (no user-controlled URLs fetched), no Octane-unsafe mutable static state beyond the schema boolean cache.

OUT-OF-SCOPE NOTES
- Missing-index check: package owns no tables; widget predicates (`chip_status`, `created_at`, `ends_at`, `trial_ends_at` on cashier-chip subscriptions) should be indexed in cashier-chip — recommend verifying there.
- `role:...` middleware string in BillingPanelProvider.php:88 assumes spatie/laravel-permission semantics; verify against the installed version in host app (not verified here).

### Prior-audit findings (verbatim chunk)

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-cashier-chip` MEDIUM perf — inheritance contract GOOD (`BaseCashierChipResource` + `assertResolvedOrExplicitGlobal`). `CustomerPortal/Invoices:59-73` safe iff `getBillable()` owner-bound — verify else HIGH.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

### Verification verdicts

- [R1:#1] CONFIRMED critical bug | src/Resources/SubscriptionResource/Pages/ListSubscriptions.php:36-60 | "Bulk pause/resume mass-updates" raw chip_status; no paused_at/events/CHIP; cross-tenant when owner off
- [R1:#2] CONFIRMED high bug | src/Resources/BaseCashierChipResource.php:47-86 | "CustomerResource SQL-crashes when" owner on: owner cols on User/Team; chip.owner half FALSE, no such key
- [R1:#3] CONFIRMED high bug | src/CustomerPortal/Pages/Subscriptions.php:156-187 | "Canceled grace-period subscriptions" excluded from list; $cancelledSubscriptions never rendered; no Resume
- [R1:#4] CONFIRMED high performance | src/Resources/CustomerResource/Tables/CustomerTable.php:62-98 | "Severe per-row N+1" count/row + defaultPaymentMethod x2 + chipId/onTrial per row
- [R1:#5] DOWNGRADED (was high) med performance | src/Widgets/MRRWidget.php:119-149 | "Dashboard fires ~60+" monthly withSum+get loops, no cache; DUP AUD:B7; withSum per FALSE-list
- [R1:#6] CONFIRMED med bug | src/Resources/CustomerResource/Pages/ListCustomers.php:34-60 | "Sync All to" unscoped unbounded sync CHIP calls in request; relation throws outside try
- [R1:#7] CONFIRMED med bug | src/Concerns/InteractsWithCashierChipData.php:31 | "Under explicit-global admin" includeGlobal:false + null owner to whereNull (OwnerQuery:46-47); zeros
- [R1:#8] CONFIRMED med bug | src/Concerns/InteractsWithCashierChipData.php:42-53 | "normalizeToMonthly() DivisionByZeroError when" count=0; domain guards, widget copy not
- [R1:#9] CONFIRMED med bug | src/Widgets/ChurnRateWidget.php:39-88 | "Churn denominators inconsistent:" status filter only in current-month path, prev/chart unfiltered
- [R1:#10] CONFIRMED med bug | src/Widgets/MRRWidget.php:41-76 | "MRR discount handling" full coupon off monthly (yearly wrong); previous ignores discounts; adapter logic
- [R1:#11] CONFIRMED med security | src/CustomerPortal/Pages/Subscriptions.php:96-102 | "Raw $e->getMessage()" to users (Subscriptions x2, PaymentMethods x2); log + generic msg
- [R1:#12] CONFIRMED med bug | src/Resources/CustomerResource/RelationManagers/SubscriptionsRelationManager.php:21 | "hardcoded $relationship='subscriptions' but" guard admits chipSubscriptions-only (:102); crash
- [R1:#13] CONFIRMED med bug | src/Resources/SubscriptionResource/Schemas/SubscriptionInfolist.php:91 | "method_exists($record->customer, 'chipId')" TypeErrors when customer null
- [R1:#14] CONFIRMED med bug | src/CustomerPortal/Pages/PaymentMethods.php:65-94 | "getAddPaymentMethodUrl() creates a" setup purchase on render; catch-all returns '#' silently
- [R1:#15] CONFIRMED med performance | src/CustomerPortal/Pages/Invoices.php:105-114 | "Unbounded invoice list:" all invoices, blade loops every row, no pagination
- [R1:#16] CONFIRMED med performance | src/Resources/BaseCashierChipResource.php:32-37 | "getNavigationBadge() runs a" count/request + asserts context (throws); DUP AUD:B3
- [R1:#17] CONFIRMED low security | src/Widgets/RevenueChartWidget.php:69 | "config currency interpolated" into JS callback; config-controlled; allowlist it
- [R1:#18] CONFIRMED low bug | src/Resources/InvoiceResource/Tables/InvoiceTable.php:160-168 | "Dead buttons: InvoiceTable.php:160-168" download_pdf empty; ListInvoices export_csv no-op
- [R1:#19] CONFIRMED low bug | src/Resources/BaseCashierChipResource.php:51 | "BaseCashierChipResource.php:51 default" true vs domain false (cashier-chip.php:41); align
- [R1:#20] CONFIRMED low bug | src/Resources/CustomerResource/RelationManagers/SubscriptionsRelationManager.php:112 | "getStatusColor(SubscriptionStatus $status) non-nullable" vs null-accepting formatter; null TypeError
- [R1:#21] CONFIRMED low bug | src/Resources/CustomerResource/Tables/CustomerTable.php:27 | "static $genericTrialQuerySupport schema" cache persists across Octane/test; keyed conn|table
- [R1:#22] CONFIRMED low bug | src/Resources/SubscriptionResource/RelationManagers/SubscriptionItemsRelationManager.php:99-148 | "quantity/price forms: quantity/unit_amount" min only, no max; price free text; admin-only
- [R1:#23] CONFIRMED low bug | src/CustomerPortal/Pages/Invoices.php:59-77 | "abort(404) inside a" Livewire action; prefer Notification + return
- [AUD:B1] ADOPTED med performance | src/Concerns/InteractsWithBillable.php:26-47 | Inheritance GOOD adopted; getBillable user/team only, no owner bind; billable-scoped, no IDOR
- [AUD:B2] ADOPTED pass nav | src/Resources/BaseCashierChipResource.php:22-30 | G1 Navigation PASS adopted; config group+sort verified
- [AUD:B3] ADOPTED med performance | src/Resources/BaseCashierChipResource.php:32-37 | G2 uncached badge COUNT verified; DUP R1:#16 (counted once)
- [AUD:B4] ADOPTED med performance | src/Resources/CustomerResource/Tables/CustomerTable.php:74-98 | G3 per-row relation queries verified; DUP R1:#4
- [AUD:B5] ADOPTED pass performance | src/Resources/CustomerResource.php:41-50 | G4 no pluck-preload select cited here; no model/form binding; plausible PASS
- [AUD:B6] ADOPTED low bug | src/Widgets/MRRWidget.php:41-76 | G5 analogue verified (MRR/normalizeToMonthly in adapter); DUP R1:#10
- [AUD:B7] ADOPTED med performance | src/Widgets/MRRWidget.php:41-76 | G6 withSum verified (:45,:67,:135); residual uncached counts; DUP R1:#5 (counted once)

---

## filament-commerce-support

### E2E findings (verbatim)

E2E review: packages/filament-commerce-support — 9 findings (3 medium bug, 2 medium, 4 low) + positives. No critical/high.

F1 — MEDIUM / bug — src/Support/NavigationConfigurator.php:45-74 (+ src/Pages/ManageCommerceNavigation.php:385-399) — Shallow per-entry merge permanently drops file-level keys. Confidence: high.
apply() uses top-level array_merge, so a settings entry for a class/group wholly replaces the file-config entry. But save() persists partial configs (item: hidden/label?/sort/parent_item?/group; group: label/icon only when non-empty). hasDifferences() only compares submitted keys, so e.g. file config label 'Foo' with a blank form label yields no label key; if sort differs the entry is saved label-less and apply() then shadows the file label forever. Same for group icons and component keys like visible.
Evidence: `config()->set('...items', array_merge(config('...items', []), $overrides));` vs `overrideFromSidebarItem()` omitting blank label/parent_item.
Recommendation: merge per entry with array_replace_recursive in apply(), or persist complete merged configs in save().

F2 — MEDIUM / bug — src/Pages/ManageCommerceNavigation.php:104-109 vs 351-369 — Group-rename round-trip mis-buckets items; resave silently detaches them. Confidence: high.
save() rewrites each item's group from key to label ($groupRenames). But buildSidebarForForm() matches strictly on key: `if ($itemGroup === $key)`. After any rename (label != key), the next mount files those items under Ungrouped, and saving from that state persists group='' — silent detachment. Rendering engine is unaffected (it matches labels too), so the corruption is manager-only and invisible until resave.
Recommendation: match by key OR label when bucketing, or store keys in settings and resolve labels at render.

F3 — MEDIUM / bug — src/Pages/ManageCommerceNavigation.php:200-205,238-242 vs 324,430 — Sort Order inputs are silently discarded. Confidence: high.
The form defines numeric Sort inputs for groups and items, but save() uses drag position only (`$groupConfig['sort'] = $groupSortIndex;`, `$config['sort'] = $index + 1;`), never reading $section['sort']/$item['sort']. Typed values are lost on every save.
Recommendation: remove the inputs or honor them (e.g. use entered value when it differs from positional default).

F4 — MEDIUM / bug (validation gap) — src/Pages/ManageCommerceNavigation.php:284-289 — save() bypasses all form validation. Confidence: high.
save() reads `$this->data['sidebar']` directly and never calls getState()/validate, so required-component, Select allowlist, maxLength(255), numeric/min/max rules never run. Arbitrary component-class strings (persisted as settings keys, feeding F8), overlong labels, negative/non-numeric sorts are all accepted. Filament rules are enforced only via getState().
Recommendation: `$state = $this->getSchema('form')?->getState()` in save(); allowlist component against CommerceNavigation::registeredNavigationComponents(); add maxLength on group_key, maxItems on repeaters, and a heroicon-name regex on icon (a bad icon value renders for all admins).

F5 — MEDIUM / bug (integration) — src/FilamentCommerceSupportPlugin.php:31-46 — Currency/Language/Timezone resources are never registered. Confidence: med.
register() only adds ManageCommerceNavigation; no $panel->resources([...]), and docs/02-installation.md shows only ->plugin(...). Filament discovery covers app paths, not package src, so the advertised reference-data UI is unreachable out-of-box unless the host manually registers it.
Recommendation: register the three resources in the plugin (gated by navigation.enabled + resource config), or document manual registration.

F6 — LOW / bug (UI) — src/Pages/ManageCommerceNavigation.php:187,192,198,205,209,213,218 — hidden() closures test each field's own state, not group_key. Confidence: high.
`->hidden(fn (?string $state) => ($state ?? '') === '__ungrouped__')` on label/icon/sort/toggles compares that field's own value, so group fields stay visible in the Ungrouped section (only the group_key field itself hides correctly). Verified against vendor CanBeHidden/evaluate ($state = own component state; sibling read needs Get). Coercive typing confirmed — cosmetic only, no crash.
Recommendation: `->hidden(fn (Get $get): bool => $get('group_key') === '__ungrouped__')`.

F7 — LOW / bug — src/Pages/ManageCommerceNavigation.php:525-550 — resolveSettings() misses QueryException and writes on GET. Confidence: high.
Unlike NavigationConfigurator::resolveSettings() (catches QueryException|MissingSettings), the page resolver catches only MissingSettings: a fresh install without the settings table 500s on mount/save instead of degrading gracefully. It also insertOrIgnores seed rows during mount() (side-effecting GET, fails on read-only replicas) with hardcoded spatie table columns.
Recommendation: catch QueryException and fall back to an empty in-memory instance with a warning notification; share one resolver with NavigationConfigurator.

F8 — LOW / security (defense-in-depth) — src/Pages/ManageCommerceNavigation.php:157-174,251-265 — Static method invoked on settings-persisted class names. Confidence: med.
normalizeOverrideForSidebar() and the itemLabel closure call `$class::getNavigationLabel()` for $class keys drawn from $settings->overrides, which F4 shows are attacker-influenceable (no allowlist). Planting requires the nav permission, and the gadget is narrow (no-arg static named getNavigationLabel), but stored class names should never be invoked.
Recommendation: only call when in_array($class, registeredNavigationComponents(), true), else class_basename($class).

F9 — LOW / bug+performance (minor nits, grouped) — various. Confidence: high/med.
(a) Duplicate component in two groups silently last-wins (save():339-348). (b) Real group keyed `__ungrouped__` is swallowed as ungrouped (:141,:298). (c) TimezoneResource::getNavigationSort() lacks the `, 100` default its siblings have (TimezoneResource.php:47-50). (d) apply() assumes settings entries are arrays; a corrupted non-array entry fatals on `$itemConfig['group']` (NavigationConfigurator.php:62-63). (e) Repeaters unbounded (no maxItems/maxLength on group_key) — settings JSON bloat is re-merged each panel boot; reads are cache-backed (verified spatie SettingsCache), so impact is small. (f) Octane/test staleness: NavigationConfigurator $captured/$original* statics never refresh in-process (no reset method) — stale snapshot after runtime config changes / cross-test pollution. Recommend allowlists, per-entry is_array guards, repeater limits, and a NavigationConfigurator::reset() for tests.

POSITIVES (verified, not guessed): canAccess() denies by default on missing/empty permission, and vendor confirms Page uses CanAuthorizeAccess with mount+hydrate hooks — save() is auth-protected on every Livewire request. Reference resources expose only index+view pages with form disabled by default read_only=true. No HasOwner/owner-scoped data in package (global reference data + global settings by design); no unscoped owner queries. No raw SQL (bound insertOrIgnore; table name config-controlled), no unserialize/file/URL handling → no injection/SSRF/traversal/deserialization surface. Blade has no unescaped output. Searchable/sorted columns carry unique indexes (currencies.code, languages.code, timezones.name) and tables are tiny seeds; pagination bounded. SettingsSaved listener ordering is correct (restore genuine defaults, then apply()). Follows repo rules: config-driven navigation.group + getNavigationGroup, no FK/SoftDeletes/money. Process note: package ships no tests/ dir although repo standard is Pest — F1/F2 merge/rename logic in particular deserves coverage.

### Prior-audit findings (verbatim chunk)

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-commerce-support` OK. `filament-communications` GOOD (all 7 scoped). `filament-contacting` GOOD. `filament-customers` GOOD exemplary. `filament-docs` GOOD exemplary + cached badge.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

### Verification verdicts

- [R1:#1] CONFIRMED med bug | src/Support/NavigationConfigurator.php:46 | "Shallow per-entry merge" array_merge intact :46,:71; save partial, submitted-keys diff :780
- [R1:#2] CONFIRMED med bug | src/Pages/ManageCommerceNavigation.php:106 | "Group-rename round-trip mis-buckets" strict key match vs label rewrite :361-369
- [R1:#3] CONFIRMED med bug | src/Pages/ManageCommerceNavigation.php:430 | "Sort Order inputs" ignored; positional sort only :324,:430, typed values lost
- [R1:#4] CONFIRMED med bug | src/Pages/ManageCommerceNavigation.php:284 | "save() bypasses all" reads $this->data directly; no getState/validate call
- [R1:#5] CONFIRMED med bug | src/FilamentCommerceSupportPlugin.php:31 | "Currency/Language/Timezone resources" never registered; pages-only register()
- [R1:#6] CONFIRMED low bug | src/Pages/ManageCommerceNavigation.php:192 | "hidden() closures test" own state not group_key :187-218, fields stay visible
- [R1:#7] CONFIRMED low bug | src/Pages/ManageCommerceNavigation.php:525 | "resolveSettings() misses QueryException" catches MissingSettings only + seeds on GET
- [R1:#8] CONFIRMED low security | src/Pages/ManageCommerceNavigation.php:161 | "Static method invoked" on settings class names, no allowlist :157-174,:251-265
- [R1:#9] CONFIRMED low bug+perf | src/Pages/ManageCommerceNavigation.php:339 | "Duplicate component in" +5 nits hold: no ,100 :47, no is_array :62, no reset()
- [AUD:B1] ADOPTED info meta | audits/.staging-verify/vpkg/audit-filament-commerce-support.md:2 | Pkg rated OK; light confirm, no contradiction in current source
- [AUD:B2] ADOPTED pass nav | audits/.staging-verify/vpkg/audit-filament-commerce-support.md:3 | G1 PASS light-confirmed: config-driven groups, no static nav props
- [AUD:B3] ADOPTED med perf | audits/.staging-verify/vpkg/audit-filament-commerce-support.md:4 | G2 badges: no getNavigationBadge in pkg; N/A here
- [AUD:B4] ADOPTED med perf | audits/.staging-verify/vpkg/audit-filament-commerce-support.md:5 | G3 N+1: no relation columns; tiny seed tables; N/A here
- [AUD:B5] ADOPTED med perf | audits/.staging-verify/vpkg/audit-filament-commerce-support.md:6 | G4 pluck+preload: no occurrences in pkg; N/A here
- [AUD:B6] ADOPTED low leak | audits/.staging-verify/vpkg/audit-filament-commerce-support.md:7 | G5 cites vouchers only; N/A here
- [AUD:B7] ADOPTED med perf | audits/.staging-verify/vpkg/audit-filament-commerce-support.md:8 | G6 cites cashier-chip/inventory only; N/A here

---

## filament-communications

### E2E findings (verbatim)

Review of packages/filament-communications (Filament v5 read-focused ops UI; no models/migrations/routes/jobs in scope — all live in `communications`).

FINDINGS

1. [medium, bug] src/Resources/CommunicationDeliveryResource.php:97-104 — Retry action lets domain RuntimeExceptions bubble to the user. `RetryCommunicationDeliveryAction::handle()` throws RuntimeException when status != failed or `attempt_count >= max_attempts` (verified in packages/communications/src/Actions/RetryCommunicationDeliveryAction.php:18-30). The `visible()` gate is render-time only, so a race (already retried / attempts exhausted) produces an unhandled exception instead of a danger Notification. Recommend try/catch around the action call with `Notification::make()->danger()->title(...)->send()`. Confidence: high.

2. [medium, bug] src/Widgets/DeliveryStatusOverviewWidget.php:20-40 — Status buckets silently drop 7 of 19 DeliveryStatus cases. Only Pending/Sent-accepted-received/Delivered-opened-read-clicked/Failed-bounced-complained-expired are counted; Suppressed, Scheduled, Queued, Sending, Replied, Unsubscribed, Cancelled appear nowhere, so the dashboard misleads on backlog/health. Recommend adding Queued/Sending to Pending (or a separate stat) and a Suppressed/Cancelled bucket, or a Total stat so buckets reconcile. Confidence: high (enum has 19 cases, verified).

3. [low, bug] src/RelationManagers/*.php + src/Resources/*.php — All three relation managers (Communications, Deliveries, CommunicationTimeline) are orphaned: no resource defines `getRelationManagers()` (grep-confirmed), and relationships exist on domain models, so this is dead code / missing wiring, not a wrong relationship. Recommend wiring them (e.g. CommunicationsRelationManager on thread resource, Deliveries + Timeline on communication resource) or deleting them. Confidence: high.

4. [low, bug] src/Resources/CommunicationResource/Pages/ViewCommunication.php:15-30 — Page-level `infolist()` duplicates CommunicationResource::infolist() verbatim; any future field change must be made twice and will drift. Recommend removing the page override so the resource infolist is the single source. Confidence: high.

5. [low, bug] All 7 Resources getNavigationSort() — every resource returns the same config `navigation.sort`, so sidebar order among the 7 resources is nondeterministic. Recommend per-resource offsets (sort, sort+1, …) or distinct config keys. Confidence: high.

6. [low, bug] CommunicationDeliveryResource.php:73-88, CommunicationThreadResource.php:65-71, CommunicationPreferenceResource.php:63-69 — channel/provider filter options are hardcoded string lists duplicated across resources with no domain enum/config backing (no Channel/Provider enum exists; domain stores plain strings). Drift risk when providers change. Recommend a shared constant/helper in `communications` (per adapter-only guardrail) consumed by all three resources. Confidence: med.

7. [low, security] All Resources + DeliveryStatusOverviewWidget — no `canViewAny`/policy/`canView` gates; any authenticated panel user sees all owner-scoped comms data (suppression hashes excluded — good — but purposes, titles, recipient IDs, costs visible). Owner scoping is correct, but there is no role/permission layer. Recommend documenting intended panel audience or adding `shouldRegisterNavigation`/policy checks if least-privilege is required. Confidence: med.

8. [low, performance] src/Widgets/DeliveryStatusOverviewWidget.php:18-40 — Four COUNT(*) subqueries over communication_deliveries run uncached on every dashboard render with no polling limit; on large delivery tables this is the heaviest query on the dashboard. Recommend short-lived cache (e.g. 60s, owner-keyed) or `getPollingInterval`. Confidence: med.

9. [low, bug] src/FilamentCommunicationsPlugin.php:44-46 — DeliveryStatusOverviewWidget is registered unconditionally with no config toggle, unlike all 7 resources. Minor inconsistency; recommend a `widgets.delivery_overview.enabled` key. Confidence: high.

NOTES / NON-FINDINGS (checked, clean)
- Owner scoping is correct everywhere it matters: all 7 resources use `OwnerUiScope::apply(..., includeGlobal: false)` in getEloquentQuery, matching domain default `include_global=false`; widget uses the same; retry action path re-resolves via `OwnerWriteGuard::findOrFailForOwner` AND the domain action's inner `findOrFail` is owner-scoped via the HasOwner global OwnerScope — no IDOR. Relation-manager children resolve through scoped parents.
- No mass assignment (no forms), no validation gaps (read-only + one confirmed action), no XSS (`TextColumn`/`TextEntry` only, no `->html()`), no injection/SSRF/path-traversal/deserialization vectors, no static Octane-unsafe state, no unbounded pagination (Filament defaults), no N+1 (no relation traversal in columns), no empty Policies/Pages stubs wired.
- Package has no tests directory; acceptable for thin UI but widget-bucket logic (finding 2) is worth a Pest test.

POSITIVES
- Consistent OwnerUiScope + OwnerWriteGuard usage with fail-closed semantics; retry is confirmation-gated and status-gated; suppressions expose only truncated destination_hash; money shown as `cost_minor` int minor units per repo rules; navigation group/sort via config per repo rules.

### Prior-audit findings (verbatim chunk)

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-commerce-support` OK. `filament-communications` GOOD (all 7 scoped). `filament-contacting` GOOD. `filament-customers` GOOD exemplary. `filament-docs` GOOD exemplary + cached badge.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

### Verification verdicts

- [R1:#1] CONFIRMED med bug | src/Resources/CommunicationDeliveryResource.php:97 | "Retry action lets" RuntimeException bubble; no try/catch :97-110, throws :21-29
- [R1:#2] CONFIRMED med bug | src/Widgets/DeliveryStatusOverviewWidget.php:20 | "Status buckets silently" count 12/19; 7 cases dropped, enum has 19
- [R1:#3] CONFIRMED low bug | src/RelationManagers/CommunicationsRelationManager.php:15 | "All three relation" managers orphaned; zero getRelationManagers in pkg
- [R1:#4] CONFIRMED low bug | src/Resources/CommunicationResource/Pages/ViewCommunication.php:17 | "Page-level infolist() duplicates" resource infolist verbatim; will drift
- [R1:#5] CONFIRMED low bug | src/Resources/CommunicationDeliveryResource.php:36 | "every resource returns" identical navigation.sort; order nondeterministic
- [R1:#6] CONFIRMED low bug | src/Resources/CommunicationDeliveryResource.php:73 | "channel/provider filter options" hardcoded dup lists across 3+ resources
- [R1:#7] CONFIRMED low security | src/Resources/CommunicationResource.php:1 | "no canViewAny/policy/canView" gates anywhere; owner scope only
- [R1:#8] CONFIRMED low perf | src/Widgets/DeliveryStatusOverviewWidget.php:18 | "Four COUNT(*) subqueries" uncached on every dashboard render
- [R1:#9] CONFIRMED low bug | src/FilamentCommunicationsPlugin.php:44 | "DeliveryStatusOverviewWidget is registered" unconditionally vs gated resources
- [AUD:B1] ADOPTED info meta | audits/.staging-verify/vpkg/audit-filament-communications.md:2 | Pkg GOOD all-7-scoped; OwnerUiScope confirmed on resources read
- [AUD:B2] ADOPTED pass nav | audits/.staging-verify/vpkg/audit-filament-communications.md:3 | G1 PASS holds; R1:#5 same-sort fragility consistent with note
- [AUD:B3] ADOPTED med perf | audits/.staging-verify/vpkg/audit-filament-communications.md:4 | G2 badges: none in pkg; N/A here
- [AUD:B4] ADOPTED med perf | audits/.staging-verify/vpkg/audit-filament-communications.md:5 | G3 N+1: no relation traversal in columns; N/A here
- [AUD:B5] ADOPTED med perf | audits/.staging-verify/vpkg/audit-filament-communications.md:6 | G4 pluck+preload: no occurrences in pkg; N/A here
- [AUD:B6] ADOPTED low leak | audits/.staging-verify/vpkg/audit-filament-communications.md:7 | G5 cites vouchers only; N/A here
- [AUD:B7] ADOPTED med perf | audits/.staging-verify/vpkg/audit-filament-communications.md:8 | G6 pattern resembles R1:#8 but cites other pkgs; kept separate

---

## filament-contacting

### E2E findings (verbatim)

End-to-end review of packages/filament-contacting (Filament v5 adapter, UI-only; 3 resources, 2 relation managers, 5 schemas, 3 tables, 2 importers, 2 exporters, config-only, no routes/migrations/tests).

FINDINGS

1) severity: high | category: security | files: src/Tables/ContactMethodTable.php:89-97, src/Tables/SocialProfileTable.php:86-94, src/RelationManagers/ContactMethodsRelationManager.php:39, src/RelationManagers/SocialProfilesRelationManager.php:32
Title: read_only mode enforced with ->visible() only — bypassable via Livewire action calls
Description: All create/edit/delete gating uses `->visible(fn () => ! $guard->...ReadOnly())`. Filament's action dispatcher (vendor/filament/actions/src/Concerns/InteractsWithActions.php:163,291,297) enforces `isDisabled()` and `isAuthorized()` when mounting/calling an action but never checks `isVisible()`. An authenticated panel user can mount and execute the hidden Create/Edit/Delete/DeleteBulk actions with a crafted Livewire request. Standalone resources additionally remove create/edit pages when read-only (routes gone), but the index table's row/bulk Edit/Delete actions remain callable, so even that path is bypassable.
Evidence: `EditAction::make()->visible(fn (): bool => ! $guard->contactMethodsReadOnly())` vs vendor `if ($action->isDisabled()) { ... return null; }` / `(! $action->isAuthorized())` with no isVisible check.
Recommendation: Add `->authorize(fn () => ! $guard->...ReadOnly())` (or `->disabled()`) to every gated action, matching the existing `visible()` closures; consider resource-level `canCreate/canEdit/canDelete` overrides.
Confidence: high

2) severity: medium | category: security | file: src/Imports/ContactMethodImporter.php:32-33
Title: Importer is_public cast defaults missing values to true, overriding core private-by-default for email/phone
Description: `castStateUsing(fn (?string $state): bool => $state !== 'false' && ...)` maps null/empty to `true`. Core `ContactMethod::applyDefaultFlags()` (packages/contacting/src/Models/ContactMethod.php:205-210) defaults email/phone/mobile/whatsapp/fax to non-public. A CSV without an is_public column therefore mass-publishes PII that the UI path would keep private.
Evidence: `ImportColumn::make('is_public')->castStateUsing(fn (?string $state): bool => $state !== 'false' && $state !== '0' && $state !== 'no')`
Recommendation: Cast null/'' to null (leave unset so model defaults apply), or default to false; add an explicit `is_public` column note in docs.
Confidence: high

3) severity: medium | category: bug | files: src/Resources/ContactMethodResource.php:65-79, src/Resources/SocialProfileResource.php:65-79, src/Schemas/ContactMethodFormSchema.php, src/Schemas/SocialProfileFormSchema.php
Title: Standalone create pages produce orphan records (no contactable/socialable fields)
Description: Neither form schema exposes the parent polymorphic reference, and the core guard explicitly allows null/null (`ContactingModelReferenceGuard::resolve()` returns null). Enabling a standalone resource + create page therefore persists parentless contact methods/social profiles — the exact failure the package docs warn is "usually an error" (docs/01-overview.md:36). `purpose` is likewise unexposed (falls back to DB default 'general', so functional but invisible).
Evidence: `ContactMethodFormSchema::make()` fields are only type/label/value/country_code/is_primary/is_public; `resolve(null, null)` returns null without error.
Recommendation: Either remove create/edit pages from standalone resources (relation-manager-only writes) or add owner-guarded parent pickers that resolve through `ContactingModelReferenceGuard`.
Confidence: high

4) severity: medium | category: bug | files: src/Imports/ContactMethodImporter.php:17-35, src/Imports/SocialProfileImporter.php:17-35
Title: Importers define zero validation rules — type/platform/value/url/country_code unchecked
Description: No `ImportColumn` declares `->rules()`. Type/platform are not checked against allowed sets (UI Selects validate against options; strict mode in core defaults off), `value` (max 2048 in UI) and `url` (UI `->url()`) accept arbitrary strings up to any length, and `country_code` (UI maxLength 2) is unbounded. A `javascript:` URL passes import validation (only neutralized later by core `NormalizesUrl`, silently importing a null url). Form-level invariants are bypassed wholesale on the import path.
Evidence: All eight/nine `ImportColumn::make(...)` calls use only `requiredMapping()`/`castStateUsing()`, no `->rules([...])`.
Recommendation: Mirror form constraints as import rules: `in:` against `ContactMethodType::options()` keys / `SocialPlatform` values, `max:2048`, `url:http,https`, `size:2`+alpha for country_code.
Confidence: high

5) severity: medium | category: bug | files: src/Imports/ContactMethodImporter.php:37-47, src/Imports/SocialProfileImporter.php:42-48
Title: Owner-guard import rejections surface with blank failure reasons
Description: `beforeValidate()` throws `InvalidArgumentException` on cross-owner/bad references. Filament's `ImportCsv` job (vendor/filament/actions/src/Imports/Jobs/ImportCsv.php:89-97) preserves messages only for `RowImportFailedException`/`ValidationException`; generic `Throwable` is reported and logged via `logFailedRow($row)` with no message. Users see failed rows with no explanation. (Positive: the per-row catch means one bad row does not abort the job.)
Evidence: `app(ContactingModelReferenceGuard::class)->resolve(...)` throwing `InvalidArgumentException` vs `} catch (Throwable $exception) { report($exception); $this->logFailedRow($row); }`.
Recommendation: Catch the guard exception in `beforeValidate()` and rethrow `RowImportFailedException` (or `ValidationException`) with a descriptive message.
Confidence: high

6) severity: medium | category: security | files: src/RelationManagers/ContactMethodsRelationManager.php, src/RelationManagers/SocialProfilesRelationManager.php
Title: Relation managers perform no owner check on the parent record
Description: Both relation managers contain no `OwnerUiScope` usage; child visibility and the attach-parent for creates derive entirely from the host resource's record resolution. If embedded in a parent resource that does not scope via `OwnerUiScope`, cross-owner children are listed and new children get their owner from `OwnerContext` (core `HasOwner::assignOwnerOnCreate`) rather than from the parent — a mismatch vector. Package guardrail requires revalidating submitted IDs against owner scope.
Evidence: Full-file inspection — zero owner references in either relation manager; only `->visible(readOnly)` gating.
Recommendation: Document that host resources MUST apply `OwnerUiScope`, and consider scoping the relation table query with `OwnerUiScope::applyForRecordOwner()` where feasible.
Confidence: med

7) severity: low | category: bug | files: src/Imports/ContactMethodImporter.php:54-60, src/Imports/SocialProfileImporter.php:50-56
Title: getModelLabel() returns FQCN instead of human-readable label
Description: Both return `ContactMethod::class` / `SocialProfile::class` (with a wrong `@return class-string` docblock); Filament renders this label in import UI/notifications, showing e.g. `AIArmada\Contacting\Models\ContactMethod`.
Recommendation: Return `'Contact Method'` / `'Social Profile'`.
Confidence: high

8) severity: low | category: bug | files: src/Tables/ContactSnapshotTable.php:37-43, src/Tables/ContactMethodTable.php:83
Title: Option-less SelectFilters render empty dropdowns
Description: `SelectFilter::make('snapshot_type'|'reason'|'channel')` and `SelectFilter::make('country_code')` declare no `->options()`/`->relationship()`/`->attribute()`, so the filter dropdowns have no choices. (The type/platform filters elsewhere correctly pass enum options.)
Recommendation: Supply options (enums/config/distinct query) or switch to `TextFilter`/`QueryBuilder`.
Confidence: high

9) severity: low | category: bug | file: config/filament-contacting.php:16-21 (consumer: none)
Title: tables.default_pagination config key is documented but never applied
Description: `ContactingFilamentConfig::defaultPagination()` has exactly one reference (its own definition); no table calls `paginationPageOptions()`/`defaultPaginationPageOption()`. Setting the key has no effect.
Recommendation: Wire it in all three table builders or remove the key + docs.
Confidence: high

10) severity: low | category: bug | files: src/Support/ContactingFilamentConfig.php, src/Support/ResolvesContactingModels.php, src/Tables/SocialProfileTable.php:37-39
Title: Dead config surface and docs/behavior drift (open_url_actions, show_owner_columns, etc.)
Description: Verified by repo-wide search: `navigationGroup/Sort/Icon`, `standaloneResources`, `relationManagersEnabled`, `importsEnabled`, `verificationBadges`, `openUrlActions`, `showOwnerColumns`, `defaultPagination`, `contactSnapshotsReadOnly`, and the entire `ResolvesContactingModels` class are never called. Consequences: (a) `SocialProfileTable` url column is plain truncated text despite `open_url_actions=true` and docs/04-usage.md:86-88 promising clickable links (only the infolist links); (b) `show_owner_columns` renders nothing; (c) no `ImportAction` is wired anywhere so `features.imports` is inert; (d) `standalone_resources` feature flag is inert — only per-resource `enabled` gates registration.
Recommendation: Implement or remove each flag; at minimum fix the url-column link and the usage doc.
Confidence: high

11) severity: low | category: bug | files: docs/04-usage.md:12-13,33, docs/02-installation.md:43, docs/02-installation.md:12 / composer.json
Title: Docs use wrong-case `AiArmada\...` namespace; version drift
Description: Snippets import `AiArmada\FilamentContacting\...` / `AiArmada\Contacting\...`, but Composer PSR-4 prefix matching is case-sensitive, so copy-pasted code throws class-not-found on case-sensitive filesystems (PHP class-name case-insensitivity does not save autoload prefix lookup). Also docs pin `filament/filament ^5.6.7` vs composer `^5.7.0`.
Recommendation: Fix casing to `AIArmada\...`; align the Filament version.
Confidence: high

12) severity: low | category: security | file: src/Schemas/SocialProfileInfolistSchema.php:28-30
Title: Infolist renders raw DB url as clickable link without scheme check (defense-in-depth)
Description: `->url(fn (?string $state): ?string => $state)` passes the stored value straight to `href`. Filament explicitly does not sanitize and warns callers to validate (`vendor/filament/schemas/src/Components/Concerns/CanOpenUrl.php:22-24`: "validate it to prevent XSS via javascript: protocol URLs"). Safe today only because core `NormalizesUrl` coerces stored urls to http(s)/null (verified: non-http `://` rejected, schemeless values get `https://` or null) and create-path overwrites `url` with the normalized value — but any future write path bypassing normalization reopens stored-XSS.
Recommendation: Allowlist the scheme in the closure (`str_starts_with($state, 'http') ? $state : null`).
Confidence: med

13) severity: low | category: bug | files: src/Imports/ContactMethodImporter.php:49-52, src/Imports/SocialProfileImporter.php:37-40
Title: Importers always insert — re-imports duplicate rows
Description: `resolveRecord()` unconditionally returns a new model; no unique-column/upsert config, so re-running an import duplicates every row.
Recommendation: Define unique columns / match-then-update, or document insert-only behavior.
Confidence: high

14) severity: low | category: bug | files: src/Schemas/ContactMethodFormSchema.php:25,43-53, src/Schemas/SocialProfileFormSchema.php:38-53
Title: Form validation gaps: no type-conditional value rules; empty social profiles allowed
Description: `value` has no email/url/phone-format rule per `type` (only label/placeholder change); `$phoneTypes` is hardcoded and can drift from `contacting.contact_methods.types`; `country_code` has `maxLength(2)` but no alpha/format rule; social form permits both `handle` and `url` empty (core normalizer returns nulls, persisting an empty profile). `make(?bool $includeCountryCode = true)` param has no non-default caller.
Recommendation: Add conditional rules (`email` for email type, `url` for website), alpha rule for country_code, `require one of handle/url`, or push validation into core actions.
Confidence: med

15) severity: low | category: performance (hygiene) | file: composer.json
Title: Unused `ysfkaya/filament-phone-input` dependency
Description: Required in composer.json but zero references in `src`; phone numbers use plain `TextInput->tel()`. Dead dependency weight.
Recommendation: Use it for phone-type inputs or drop the requirement.
Confidence: high

POSITIVES (verified)
- Owner scoping: all 3 resources override `getEloquentQuery()` with `OwnerUiScope::apply(..., includeGlobal: false)` (fail-closed on missing config, global-context-safe per `OwnerQuery`: null owner → global-only rows); both exporters scope via `modifyQuery()`; both importers resolve every CSV parent reference through core `ContactingModelReferenceGuard` before validation.
- Secure defaults: standalone resources disabled by default; snapshots have no create/edit pages and a view-only table; `is_verified`/`verified_at`/normalized fields excluded from forms; `owner_*` not fillable on any core model (mass-assignment safe).
- Correct Filament v5 usage: `Filament\Schemas\Schema`, `Filament\Actions\*`, `Plugin::register/boot`, per-resource `getNavigationGroup/Icon/Sort` from config; no raw SQL, `DB::table`, unscoped counts, route bindings, jobs, commands, or widgets in scope.
- No XSS sinks besides F12 (TextEntries escape; metadata JSON rendered as text); no SSRF/path-traversal/deserialization vectors; no `unserialize`, file paths, or outbound requests.
- No N+1 (no relationship columns; `display_value` is a plain attribute), no unbounded listing (Filament paginates; exports go through Filament's queued chunked exporter), no cache/session/static state (Octane-safe), no SoftDeletes.
- Core backstops verified: primary-sync uses transactions + `lockForUpdate` with partial-unique DB backstop; domain migrations index type/purpose/flags; import job catches per-row `Throwable` so one bad row cannot abort an import.

OUT OF SCOPE / NOT APPLICABLE
- No migrations/routes/tests exist in this package (verified by glob); money/SoftDeletes/FK rules are domain concerns owned by `contacting`; no cache, queues, files, or money handling here.

### Prior-audit findings (verbatim chunk)

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-commerce-support` OK. `filament-communications` GOOD (all 7 scoped). `filament-contacting` GOOD. `filament-customers` GOOD exemplary. `filament-docs` GOOD exemplary + cached badge.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

### Verification verdicts

- [R1:#1] CONFIRMED high security | src/Tables/ContactMethodTable.php:89 | "read_only mode enforced" visible-only; vendor checks disabled/authz, never isVisible
- [R1:#2] CONFIRMED med security | src/Imports/ContactMethodImporter.php:32 | "Importer is_public cast" null->true vs core private default email/phone :205
- [R1:#3] CONFIRMED med bug | src/Resources/ContactMethodResource.php:65 | "Standalone create pages" lack parent fields; resolve(null,null) returns null
- [R1:#4] CONFIRMED med bug | src/Imports/ContactMethodImporter.php:17 | "Importers define zero" rules; no ->rules on any ImportColumn in pkg
- [R1:#5] CONFIRMED med bug | src/Imports/ContactMethodImporter.php:37 | "Owner-guard import rejections" blank: vendor drops generic Throwable msg :93-96
- [R1:#6] CONFIRMED med security | src/RelationManagers/ContactMethodsRelationManager.php:1 | "Relation managers perform" no owner check; OwnerUiScope only in resources
- [R1:#7] CONFIRMED low bug | src/Imports/ContactMethodImporter.php:57 | "getModelLabel() returns FQCN" shown in import UI/notifications, both importers
- [R1:#8] CONFIRMED low bug | src/Tables/ContactSnapshotTable.php:38 | "Option-less SelectFilters render" empty: no options :37-43 plus country_code :83
- [R1:#9] CONFIRMED low bug | config/filament-contacting.php:16 | "tables.default_pagination config" defined once, zero callers, setting inert
- [R1:#10] CONFIRMED low bug | src/Support/ContactingFilamentConfig.php:24 | "Dead config surface" 8 methods + ResolvesContactingModels uncalled; url plain text
- [R1:#11] CONFIRMED low bug | docs/04-usage.md:12 | "Docs use wrong-case" AiArmada in 3 docs; ^5.6.7 vs composer ^5.7.0
- [R1:#12] CONFIRMED low security | src/Schemas/SocialProfileInfolistSchema.php:28 | "Infolist renders raw" url to href; safe only via NormalizesUrl http(s) coercion
- [R1:#13] CONFIRMED low bug | src/Imports/ContactMethodImporter.php:49 | "Importers always insert" resolveRecord always new; re-import duplicates rows
- [R1:#14] CONFIRMED low bug | src/Schemas/ContactMethodFormSchema.php:43 | "Form validation gaps" no conditional value rules; empty social profile allowed
- [R1:#15] CONFIRMED low perf | composer.json:10 | "Unused filament-phone-input dependency" required, zero src references
- [AUD:B1] ADOPTED info meta | audits/.staging-verify/vpkg/audit-filament-contacting.md:2 | Pkg GOOD; OwnerUiScope on all 3 resources + exporters confirmed
- [AUD:B2] ADOPTED pass nav | audits/.staging-verify/vpkg/audit-filament-contacting.md:3 | G1 PASS light-confirmed: config-driven group/sort/icons
- [AUD:B3] ADOPTED med perf | audits/.staging-verify/vpkg/audit-filament-contacting.md:4 | G2 badges: none in pkg; N/A here
- [AUD:B4] ADOPTED med perf | audits/.staging-verify/vpkg/audit-filament-contacting.md:5 | G3 N+1: no relationship columns; N/A here
- [AUD:B5] ADOPTED med perf | audits/.staging-verify/vpkg/audit-filament-contacting.md:6 | G4 pluck+preload: no occurrences in pkg; N/A here
- [AUD:B6] ADOPTED low leak | audits/.staging-verify/vpkg/audit-filament-contacting.md:7 | G5 cites vouchers only; N/A here
- [AUD:B7] ADOPTED med perf | audits/.staging-verify/vpkg/audit-filament-contacting.md:8 | G6 cites cashier-chip/inventory only; N/A here

---

## filament-customers

### E2E findings (verbatim)

End-to-end review of packages/filament-customers (Filament UI-only package; no routes/migrations/tests dirs exist). All findings below were verified by reading the cited bodies plus cross-package contracts (customers models/actions/policies, commerce-support OwnerUiScope/OwnerWriteGuard, contacting HasContactMethods, artisan commands).

HIGH

1. [bug/high] SegmentRebuildPage calls a nonexistent artisan command — src/Pages/SegmentRebuildPage.php:93. `Artisan::call('customers:rebuild-segment', ['segment' => $segmentId])` but the only registered command is `customers:rebuild-segments {--segment=...}` (packages/customers/src/Console/Commands/RebuildSegmentsCommand.php:24). Every single-segment rebuild throws CommandNotFoundException (500). Fix: call `customers:rebuild-segments` with `--segment` option, or better dispatch a queued job. Confidence: high.

2. [bug/high] AddressValidationPage batch action calls a nonexistent command — src/Pages/AddressValidationPage.php:110. `Artisan::call('customers:validate-addresses')` matches zero commands in the monorepo (grep-verified). Always 500s. Fix: implement the command/queued job or remove the action. Confidence: high.

3. [bug/high] Missing Blade views for two pages — src/Pages/SegmentRebuildPage.php:22 (`pages.segment-rebuild`) and src/Pages/AddressValidationPage.php:26 (`pages.address-validation`); resources/views/pages/ contains only merge-customers.blade.php. Both pages 500 when enabled (they are behind default-off feature flags + plugin opt-in, so latent until activated). Fix: add the views. Confidence: high.

4. [bug/high] Segment status conditions silently ignored — src/Resources/SegmentResource/Schemas/SegmentForm.php:112-119 stores `value_status`, but Segment::applyConditions (packages/customers/src/Models/Segment.php:346) normalizes only `value_numeric ?? value_boolean ?? value` — `value_status` is never read, so `$value === null` → condition skipped. Automatic segments with a status rule match as if the rule didn't exist. Fix: persist the status under `value` (matching the documented public API) or extend applyConditions. Confidence: high.

5. [security/high] MergeCustomersPage::merge has no authorization — src/Pages/MergeCustomersPage.php:144-167. Any panel user reaching the page can merge (= mutate target + permanently delete source) any two owner-scoped customers; there is no Gate/policy check (update on both, delete on source), unlike the bulk actions and SegmentsTable::rebuild elsewhere in this package. Companion gaps: no server-side target!==source recheck in merge() (only a field rule), and core MergeCustomers throws raw InvalidArgumentException on cross-owner (owner-disabled mode) → unhandled 500 instead of a validation error. Fix: authorize update+delete via policies, recheck distinct IDs, catch and notify. Confidence: high.

MEDIUM

6. [security+performance/medium] SegmentRebuildPage actions unauthenticated-by-policy and synchronous — src/Pages/SegmentRebuildPage.php:69-109. rebuildSegment() skips the `rebuild` policy check that SegmentsTable.php:104 correctly enforces, and both it and rebuildAllSegments() run Artisan::call synchronously in the web request (timeout risk on large segments). Fix: Gate::authorize('rebuild'), dispatch async. Confidence: high.

7. [security/medium] AddressValidationPage::validateAddress lacks update authorization — src/Pages/AddressValidationPage.php:92-106. OwnerWriteGuard scoping is applied but any page user can mark any scoped address `verified` without an `update` policy check. Fix: Gate::authorize('update', $address). Confidence: high.

8. [security/medium] AddressesRelationManager attach select is unscoped — src/Resources/CustomerResource/RelationManagers/AddressesRelationManager.php:99-103. AttachAction::preloadRecordSelect() has no OwnerUiScope constraint, so the picker can surface and attach cross-owner Address rows; EditAction additionally edits the shared Address row in place (affects every customer sharing it). Fix: recordSelectOptionsQuery scoped by owner, or create-only flow. Confidence: med (policy may mitigate edit, but attach picker is visibly unscoped).

9. [bug/medium] Sortable on `full_name` accessor breaks — src/Resources/CustomerResource/Tables/CustomersTable.php:34-38. `full_name` is an accessor (getFullNameAttribute), not a column; plain ->sortable() generates ORDER BY `full_name` → SQL error when the header is clicked. Fix: sortQueryUsing on first_name/last_name or drop sortable. Confidence: med-high.

10. [bug/medium] Dead null-handling after findOrFailForOwner — src/Pages/MergeCustomersPage.php:155-165 + :193-203 and src/Pages/SegmentRebuildPage.php:71-82. OwnerWriteGuard::findOrFailForOwner throws AuthorizationException on miss/cross-owner (ResolveOwnedModelOrFailAction.php:87) and never returns null, so the "not found" notifications are unreachable when owner scoping is on (inconsistent with the owner-disabled path that does return null). Secure failure mode, but dead code + hostile UX (403 page in Livewire). Fix: try/catch → notification. Confidence: high.

11. [performance/medium] N+1 via resolveEmail()/resolveCustomer() — CustomersTable.php:38, RecentCustomersWidget.php:32, MergeCustomersPage.php:93-142. Verified HasContactMethods::resolveContact builds a fresh query on every call (no relation reuse), so each table/widget row costs an extra query, and each merge-search keystroke costs up to 20 × (OwnerWriteGuard lookup + email query) via getCustomerLabel(). Fix: eager-load/select primary emails once; reuse already-fetched rows for labels. Confidence: high.

12. [performance/medium] Unbounded segment list + per-row counts — SegmentRebuildPage.php:51-67. getSegments() has no limit and calls $segment->customers()->count() per row (N+1). Fix: withCount('customers') + pagination/limit. Confidence: high.

13. [performance/medium] Unbounded customer preload in SegmentForm — src/Resources/SegmentResource/Schemas/SegmentForm.php:145-154. Relationship multi-select with ->preload() loads every owner-scoped customer into the form. Fix: drop preload (searchable async) or cap. Same pattern, lower impact: CustomersTable segments filter pluck (CustomersTable.php:80-83). Confidence: high.

LOW

14. [performance/low] Navigation badges query on every admin request — CustomerResource.php:44-50, SegmentResource.php:40-46. Two COUNT queries per page load with no caching. Consider short-TTL cache via OwnerCache. Confidence: high.
15. [bug/low] LIKE wildcards unescaped in merge search — MergeCustomersPage.php:105-114. `%`, `_`, `\` in input act as wildcards (bound params, so no SQLi — just overbroad matches). Escape with addcslashes. Confidence: high.
16. [bug/low] Validation gaps: NotesRelationManager content has no maxLength (unbounded note bodies); AddressesRelationManager country_code accepts any 2 chars (no case/format rule). Confidence: high.
17. [process/low] Package ships zero tests (no tests/ dir) for merge, segment sync guards, and scope helpers — the exact logic carrying the 403/fail-closed guarantees above. Confidence: high.

POSITIVES (brief): owner scoping is otherwise consistent — getEloquentQuery + OwnerUiScope(includeGlobal:false) on both resources, badges, widgets, and relationship queries; syncManualSegments/syncManualCustomers correctly re-resolve owner scope and 403 on forged IDs; bulk marketing actions and SegmentsTable rebuild correctly Gate-authorize per record; policies (Customer/Segment/Address/CustomerNote) exist with owner-aware checks; destructive actions use requiresConfirmation; merge search is limit(20), unvalidated addresses limit(100); navigation follows the config-group + getNavigationGroup convention; MergeCustomersAction stays a thin UI caller over the core domain action; no raw SQL injection, XSS sinks, SSRF, path traversal, deserialization, FK violations, SoftDeletes, or Octane-unsafe static state found in this package.

NOT VERIFIED / non-issues checked: Filament relationship columns (`segments.name`) likely benefit from Filament's own eager loading — not claimed as N+1; CustomerStatsWidget date math is CarbonImmutable-safe (no mutation bug); `callable $get` rule closure is compatible with invokable Filament Get; notification bodies with customer names go through Filament's escaped rendering.

### Prior-audit findings (verbatim chunk)

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-commerce-support` OK. `filament-communications` GOOD (all 7 scoped). `filament-contacting` GOOD. `filament-customers` GOOD exemplary. `filament-docs` GOOD exemplary + cached badge.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

### Verification verdicts

- [R1:#1] CONFIRMED high bug | src/Pages/SegmentRebuildPage.php:93 | "SegmentRebuildPage calls nonexistent" command; only customers:rebuild-segments exists
- [R1:#2] CONFIRMED high bug | src/Pages/AddressValidationPage.php:110 | "AddressValidationPage batch action" command matches zero signatures monorepo-wide
- [R1:#3] CONFIRMED high bug | src/Pages/SegmentRebuildPage.php:22 | "Missing Blade views" both pages 500; only merge-customers.blade.php exists
- [R1:#4] CONFIRMED high bug | src/Resources/SegmentResource/Schemas/SegmentForm.php:112 | "Segment status conditions" dropped by applyConditions :346; service path OK
- [R1:#5] CONFIRMED high security | src/Pages/MergeCustomersPage.php:144 | "MergeCustomersPage::merge has" no Gate; no server recheck; raw InvalidArgumentException
- [R1:#6] CONFIRMED med sec+perf | src/Pages/SegmentRebuildPage.php:69 | "SegmentRebuildPage actions unauthenticated" no rebuild Gate + sync Artisan::call
- [R1:#7] CONFIRMED med security | src/Pages/AddressValidationPage.php:92 | "AddressValidationPage::validateAddress lacks" update Gate; scoping only
- [R1:#8] CONFIRMED med security | src/Resources/CustomerResource/RelationManagers/AddressesRelationManager.php:100 | "AddressesRelationManager attach select" unscoped preload; shared-row in-place edit
- [R1:#9] CONFIRMED med bug | src/Resources/CustomerResource/Tables/CustomersTable.php:34 | "Sortable on full_name" accessor ORDER BY fails; accessor in HasCustomerLifecycle
- [R1:#10] CONFIRMED med bug | src/Pages/MergeCustomersPage.php:155 | "Dead null-handling after" findOrFailForOwner throws AuthorizationException :87
- [R1:#11] CONFIRMED med perf | src/Resources/CustomerResource/Tables/CustomersTable.php:38 | "N+1 via resolveEmail()" fresh query per call, no relation reuse
- [R1:#12] CONFIRMED med perf | src/Pages/SegmentRebuildPage.php:51 | "Unbounded segment list" no limit + per-row customers()->count()
- [R1:#13] CONFIRMED med perf | src/Resources/SegmentResource/Schemas/SegmentForm.php:145 | "Unbounded customer preload" relationship+preload; segments filter pluck too
- [R1:#14] CONFIRMED low perf | src/Resources/CustomerResource.php:43 | "Navigation badges query" uncached COUNTs on every admin page load
- [R1:#15] CONFIRMED low bug | src/Pages/MergeCustomersPage.php:105 | "LIKE wildcards unescaped" %_\\ input acts as wildcards :105-114, bound so no SQLi
- [R1:#16] CONFIRMED low bug | src/Resources/CustomerResource/RelationManagers/NotesRelationManager.php:33 | "Validation gaps: NotesRelationManager" no maxLength; country any 2 chars
- [R1:#17] CONFIRMED low process | src/Pages/MergeCustomersPage.php:144 | "Package ships zero" tests; no tests/ dir, merge/sync/scope logic uncovered
- [AUD:B1] ADOPTED info meta | audits/.staging-verify/vpkg/audit-filament-customers.md:2 | Pkg GOOD exemplary; scoping + per-record Gates confirmed
- [AUD:B2] ADOPTED pass nav | audits/.staging-verify/vpkg/audit-filament-customers.md:3 | G1 PASS light-confirmed: config groups + getNavigationGroup
- [AUD:B3] ADOPTED med perf DUP R1:#14 | audits/.staging-verify/vpkg/audit-filament-customers.md:4 | G2 uncached badges identical to R1:#14; counted once
- [AUD:B4] ADOPTED med perf | audits/.staging-verify/vpkg/audit-filament-customers.md:5 | G3 plausible: segments.name/createdBy.name cols, no with() seen
- [AUD:B5] ADOPTED med perf DUP R1:#13 | audits/.staging-verify/vpkg/audit-filament-customers.md:6 | G4 pluck+preload identical to R1:#13; counted once
- [AUD:B6] ADOPTED low leak | audits/.staging-verify/vpkg/audit-filament-customers.md:7 | G5 cites vouchers only; N/A here
- [AUD:B7] ADOPTED med perf | audits/.staging-verify/vpkg/audit-filament-customers.md:8 | G6 cites cashier-chip/inventory only; N/A here

---

## filament-docs

### E2E findings (verbatim)

End-to-end review of packages/filament-docs (UI adapter over packages/docs). All findings verified by reading bodies in this package plus cross-checks in docs/commerce-support/filament-chip. No tests exist in this package (no tests/ dir) — findings are from code inspection, not test runs.

## CRITICAL

**C1 — security — any viewer can create/edit/delete everything**
Files: src/Resources/DocResource.php:60-73, DocTemplateResource.php:52-65, DocSequenceResource.php:59-72, DocEmailTemplateResource.php:64-77
`canCreate/canEdit/canDelete` use `hasAnyAbility(['purchase.create','purchase.viewAny'])` (etc.), so holding read-only `purchase.viewAny` grants full write on documents, templates, sequences, and email templates. Every other package reviewed uses the exact ability per action (e.g. filament-* `canCreate → 'x.create'` only). Evidence: `return FilamentPermission::hasAnyAbility(['purchase.create', 'purchase.viewAny']);`. Recommendation: gate each action on its own ability only. Confidence: high.

## HIGH

**H1 — security — docs UI shares CHIP `purchase.*` ability namespace**
Files: same four resources + src/Pages/AgingReportPage.php:43, PendingApprovalsPage.php:68; cf. packages/filament-chip/src/Resources/PurchaseResource.php:34,39 (different domain: CHIP payment purchases, read-only).
Granting purchase-view for payments silently grants document viewing (and via C1, document write). Recommendation: introduce dedicated `document.*`/`docs.*` abilities and migrate. Confidence: high (shared string verified); medium on exploitability (depends on role design).

**H2 — bug — arbitrary status jumps bypass the state machine**
Files: src/Resources/DocResource/Schemas/DocForm.php:93-97; packages/docs/src/Services/DocService.php:208-246; packages/docs/src/Models/Doc.php:87-111 (status fillable).
The form exposes a free status Select; `DocService::update()` does `$doc->update($data)` with no transition validation, so draft→paid skips `transitionTo()` guards, transition timestamps, and status-history audit rows (`transitionStatusTo` is only used by the dedicated status actions). Recommendation: remove direct status editing from the form (or route through `updateStatus()`), and add a transition guard in `DocService::update`. Confidence: high.

**H3 — bug — payments created/edited via RelationManager bypass DocPaymentRecorder**
File: src/Resources/DocResource/RelationManagers/PaymentsRelationManager.php:100-135,142-160; cf. packages/docs/src/Services/DocPaymentRecorder.php:25-89 (lockForUpdate, owner guard, currency match, remaining-balance cap, Paid/PartiallyPaid transitions) and DocPayment.php:68-91 (no created/saved recalc hook).
Default relationship create/edit skips the recorder: overpayment allowed (no remaining-balance check, unlike RecordPaymentAction's maxValue), no row lock, and doc status never transitions to Paid/PartiallyPaid (stale stats/aging). Delete-payment also never reverses status. Recommendation: route RM create/edit/delete through `DocPaymentRecorder`/domain methods or replicate its guards. Confidence: high.

**H4 — bug/security — doc_number and template slug uniqueness not owner-scoped**
Files: DocForm.php:59 (`->unique(ignoreRecord: true)`), DocTemplateForm.php:48; contrast DocEmailTemplateResource.php:271-303 (`scopeUniqueRuleToOwner`).
Causes cross-owner uniqueness collisions and lets a user probe existence of another owner's numbers/slugs via validation errors. Recommendation: apply the same owner-scoped unique rule. Confidence: high on the code gap; medium on enumeration impact.

## MEDIUM

**M1 — bug — RecordPaymentAction leaks domain exceptions as 500s**
File: src/Actions/RecordPaymentAction.php:104-122. No try/catch (unlike SendEmailAction which catches Throwable and notifies), so the recorder's InvalidArgumentException on overpayment/race/currency mismatch bubbles to a Livewire error instead of a validation message. Amount/paid_at constraints exist only as form rules. Recommendation: catch and surface as notification/ValidationException. Confidence: high.

**M2 — bug — SendEmailAction offers templates that always fail**
File: src/Actions/SendEmailAction.php:45-60 vs packages/docs/src/Services/DocEmailService.php:102-121 (`resolveTemplate` enforces `trigger='send'`, owner scope, doc_type, active). The dropdown filters only by doc_type+active, so picking a reminder/paid-trigger template always throws → generic "Email Failed". (Cross-owner template submission itself is safe — domain re-scopes.) Recommendation: filter options by `trigger='send'`. Confidence: high.

**M3 — performance — aging summary loads all docs into memory**
File: src/Pages/AgingReportPage.php:199-238. Unbounded `->get()` of all payable docs per page render plus `CarbonImmutable::now()` per row/bucket. Recommendation: aggregate buckets in SQL (CASE/pivot) or cache. Confidence: high.

**M4 — performance — bulk PDF generation runs synchronously in-request**
File: src/Resources/DocResource/Tables/DocsTable.php:198-208. Loops `generatePdf(save:true)` per selected record; large selections risk timeouts/memory. Recommendation: chunk and dispatch to queue with progress notification. Confidence: medium.

**M5 — security — unassigned approvals approvable by any user with page access**
File: src/Resources/DocResource/RelationManagers/ApprovalsRelationManager.php:240-253 (`assigned_to === null → return true`, only 403 otherwise; no permission check beyond resource view + C1). May be intended "any approver" flow, but combined with C1 the approver set is effectively all viewers. Recommendation: require an explicit approval ability for unassigned items. Confidence: medium.

**M6 — bug — bulk mark-as-sent skips per-record validity, no transaction**
File: DocsTable.php:210-219 vs single-action `canMarkAsSent` (DocsTable.php:237-240). Invalid transitions throw mid-loop → partial completion. Recommendation: filter to eligible records and wrap in a transaction. Confidence: medium.

## LOW

**L1 — perf — dashboard widgets fan out ~15 uncached count queries**
DocStatsWidget.php:26-36 (7 queries), StatusBreakdownWidget.php:46-47 (up to 8), DocTemplateResource.php:104 (uncached badge), PendingApprovalsPage badge+blade double-count (:54-59 + blade :15). Recommendation: single GROUP BY / reuse DocResource's OwnerCache pattern. Confidence: high.
**L2 — bug/maint — DocsOwnerScope is dead code** (src/Support/DocsOwnerScope.php; zero usages) though CONTEXT.md names it the owner/security surface; owner checks rely on OwnerUiScope + domain guards instead. Either use it in Actions/RMs or remove. Confidence: high.
**L3 — bug — email-template duplicate slug can collide** (DocEmailTemplateResource.php:210-217, timestamp suffix; same-second dupes collide) and gives no feedback notification. Confidence: high.
**L4 — bug — PendingApprovalsPage duplicates approve/reject bodies** (:156-195) instead of calling `DocApproval::approve()/reject()` (thin wrappers today — same behavior, single-source-of-truth issue only, verified in docs/Models/DocApproval.php:137-153). Confidence: high.
**L5 — correctness — minor**: temporaryUrl expiry `addMinutes(...)->endOfHour()` extends past configured minutes (DocsRichContentFileAttachmentProvider.php:44-47); RevenueChartWidget `DATE(paid_at)` + GROUP BY alias is MySQL/SQLite-leaning (RevenueChartWidget.php:35-42); form validation gaps (currency free text maxLength 3, tax_rate unbounded numeric, repeater name/qty/price without maxLength/max, DocForm.php:111-124,191-228). Confidence: medium.

## Positives (brief)
- Consistent `OwnerUiScope::apply(..., includeGlobal:false)` on all resource queries, pages, widgets, badges; navigation via `config('filament-docs.navigation.group')` + `getNavigationGroup()` per repo rules; money consistently int minor units with MoneyFormatter.
- Domain layer correctly re-guards what the UI passes through: template resolution owner/doc_type/trigger-scoped, payment recorder locked + balance-capped, DocDownload/DocPreview controllers accept `Doc|string` and re-check via OwnerWriteGuard (route-binding string fallback is safe).
- Rich-content file provider validates IDs via `DocRichContentStorage::isAllowedFileId`; blades escape output (`{{ }}`); SendEmailAction logs failures without leaking internals; Approvals RM revalidates assignee server-side; DocPayment inherits owner from doc on create.
- No migrations/FKs/SoftDeletes in package (adapter-only, compliant); no Octane-unsafe static state; no injection/SSRF/path-traversal/deserialization sinks found (no raw SQL with input, no unserialize, no file-path input).

## Not verified / out of scope
- Whether spatie state config would reject direct status assignment (code path shows no enforcement; not runtime-tested). No package tests to run (Pest suite absent here).
- Filament exporter base-query scoping for DocExporter (modifyQuery only adds withSum; presumed scoped via table query — not traced into vendor).

### Prior-audit findings (verbatim chunk)

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-commerce-support` OK. `filament-communications` GOOD (all 7 scoped). `filament-contacting` GOOD. `filament-customers` GOOD exemplary. `filament-docs` GOOD exemplary + cached badge.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

### Verification verdicts

- [R2:C1] CONFIRMED critical security | packages/filament-docs/src/Resources/DocResource.php:60-73 | canCreate/Edit/Delete all pass on purchase.viewAny; same in all 4 resources. Gate each on its own ability.
- [R2:H1] CONFIRMED high security | packages/filament-docs/src/Pages/AgingReportPage.php:41-44 | shares purchase.viewAny with chip PurchaseResource:32-40; payment view grants doc view, plus write via C1.
- [R2:H2] CONFIRMED high bug | packages/filament-docs/src/Resources/DocResource/Schemas/DocForm.php:93-97 | free status Select; EditDoc->DocService::update $doc->update skips transitionTo guards, timestamps, history.
- [R2:H3] CONFIRMED high bug | packages/filament-docs/src/Resources/DocResource/RelationManagers/PaymentsRelationManager.php:100-135 | RM create/edit/delete skip recorder: no balance cap/lock/transitions; DocPayment has no recalc hook.
- [R2:H4] CONFIRMED high bug/security | packages/filament-docs/src/Resources/DocResource/Schemas/DocForm.php:59 | doc_number + template slug unique() unscoped; cross-owner collisions/probing. EmailTemplate scoping is the fix pattern.
- [R2:M1] CONFIRMED medium bug | packages/filament-docs/src/Actions/RecordPaymentAction.php:104-122 | no try/catch; recorder InvalidArgumentException becomes Livewire 500, not a validation message.
- [R2:M2] CONFIRMED medium bug | packages/filament-docs/src/Actions/SendEmailAction.php:45-60 | dropdown omits trigger filter; non-send picks always throw in resolveTemplate -> generic Email Failed.
- [R2:M3] CONFIRMED medium perf | packages/filament-docs/src/Pages/AgingReportPage.php:199-238 | getAgingSummary unbounded get() + per-row now(); aggregate buckets in SQL or cache.
- [R2:M4] CONFIRMED medium perf | packages/filament-docs/src/Resources/DocResource/Tables/DocsTable.php:198-208 | bulk generate_pdfs loops generatePdf(save:true) in-request; chunk + queue large selections.
- [R2:M5] CONFIRMED medium security | packages/filament-docs/src/Resources/DocResource/RelationManagers/ApprovalsRelationManager.php:240-253 | assigned_to null lets any page viewer approve; require explicit approval ability.
- [R2:M6] DOWNGRADED (was medium) low bug | packages/filament-docs/src/Resources/DocResource/Tables/DocsTable.php:210-219 | markAsSent no-ops on ineligible (Doc.php:257-262): no throw/partial fail; residual is silent skip.
- [R2:L1] CONFIRMED low perf | packages/filament-docs/src/Widgets/DocStatsWidget.php:26-36 | 7 uncached queries here, 8 in StatusBreakdown, uncached template badge, approvals badge/blade double-count.
- [R2:L2] CONFIRMED low maint | packages/filament-docs/src/Support/DocsOwnerScope.php:11-26 | zero code usages (CONTEXT.md refs only); checks live in OwnerUiScope. Remove or wire in.
- [R2:L3] CONFIRMED low bug | packages/filament-docs/src/Resources/DocEmailTemplateResource.php:210-217 | duplicate slug uses second-resolution timestamp; same-second dupes collide; no feedback notification.
- [R2:L4] CONFIRMED low bug | packages/filament-docs/src/Pages/PendingApprovalsPage.php:156-195 | approve/reject inline update duplicates DocApproval::approve/reject:137-153; same behavior, SST issue only.
- [R2:L5] CONFIRMED low correctness | packages/filament-docs/src/Rendering/DocsRichContentFileAttachmentProvider.php:44-46 | endOfHour stretches URL expiry; DATE(paid_at)+alias GROUP BY; currency/tax/item gaps (file moved to Rendering/).
- [AUD:B1] ADOPTED info positive | packages/filament-docs/src/Resources/DocResource.php:134-154 | audit GOOD exemplary; cached OwnerCache + SUM(CASE) badge confirmed in current source.
- [AUD:B2] ADOPTED info positive | packages/filament-docs/src/Resources/DocResource.php:157-160 | G1 navigation PASS: config getNavigationGroup, no static group in all 4 resources + pages.
- [AUD:B3] ADOPTED medium perf | packages/filament-docs/src/Resources/DocTemplateResource.php:102-107 | G2 uncached badge; main badge cached so kept low. DUP R2:L1, counted once there.
- [AUD:B4] ADOPTED medium perf | packages/filament-docs/src/Resources/DocResource/Tables/DocsTable.php:94-96 | G3 N+1: pkg not in offender list; light-confirm pass; template.name column minor residual.
- [AUD:B5] ADOPTED medium perf | packages/filament-docs/src/Resources/DocResource/Schemas/DocForm.php:71-85 | G4 whole-table pluck: pkg not in clusters; light-confirm pass; doc_type-scoped pluck minor residual.
- [AUD:B6] ADOPTED low maint | audits/.staging-verify/vpkg/audit-filament-docs.md:7 | G5 domain leakage cites vouchers pkg only; nothing cited for filament-docs.
- [AUD:B7] ADOPTED medium perf | audits/.staging-verify/vpkg/audit-filament-docs.md:8 | G6 collection sums cites cashier-chip/inventory only; nothing cited for filament-docs.


# Part 2 — Round-2 packages (38, grouped by review batch)

---

## Round 2 — `filament-cashier`, `ticketing`, `pricing`, `filament-chip`

### E2E findings (verbatim)

# End-to-end review: filament-cashier, ticketing, pricing, filament-chip

## Positives (brief)
- Ticketing models consistently use `HasOwner+HasOwnerScopeConfig`, uuid PKs, no FK cascades, `TicketingOwnerGuard` on saving; migrations use `uuid+index`, `nullableMorphs`.
- Pricing models enforce owner write-guards (`OwnerContext::resolve`, `belongsToOwner` checks) and validate scoped refs; money in int minor units.
- Filament-chip `SendInstruction`/`BankAccount` tables re-validate owner via `forOwner()->whereKey()` before destructive actions; `CreateSendInstruction` re-checks verified status.
- Filament-cashier `CreateSubscription` re-validates billable + payment-method ownership server-side; portal `ManageSubscriptions::findSubscription` scopes by `OwnerScopedQuery` + billable.
- Blade views use `{{ }}` escaping; no `{!! !!}`, `DB::raw` with user input, `unserialize`, `eval`, path traversal found.

---

## ticketing

**1. critical/security — `packages/ticketing/src/Actions/TransferPassToHolderAction.php:17` + `BulkTransferPassesAction.php:24` + `Services/DefaultPassTransferService.php:18` — Transfer actions never enforce `PassTransferPolicy`/Gate**
Description: `PassTransferPolicy::transfer` checks `isValid`, expiry, current-holder ownership, and is registered via `Gate::policy(Pass::class)`, but neither action nor service calls `Gate::authorize/can` or the policy. Any caller with action access can transfer any pass, including expired/invalid or others' passes (subject only to global owner scope).
Evidence: `TransferPassToHolderAction::handle(){ $resolvedHolder=...; return app(PassTransferServiceInterface::class)->transfer(...);}` with no auth; `DefaultPassTransferService::transfer` only checks `canTransfer` (valid+expiry).
Recommendation: Call `Gate::authorize('transfer',$pass)` (or inject policy) in both actions; also enforce in service as defense-in-depth. Add tests for cross-holder/expired denial.
Confidence: high.

**2. high/security — `packages/ticketing/src/Actions/BulkTransferPassesAction.php:58` — Bulk event/job uses first pass owner only; mixed-owner bulk possible if scope bypassed**
Description: After `whereIn()->get()` (owner-scoped by default), event/job context is `$passes->first()?->owner_*`. If caller runs in explicit-global or scope-disabled context, passes of mixed owners can be bulk-transferred in one txn and notified under wrong owner context.
Evidence: `$event=new PassesBulkTransferred($passes,$newHolders->first(),$reason); $ownerType=$passes->first()?->owner_type; dispatch(new BulkSendTransferNotificationsJob(event:$event,ownerType:$ownerType,...))`.
Recommendation: Reject mixed `owner_type/owner_id` sets; derive job context per-pass or require single-owner batch.
Confidence: med.

**3. high/bug — `packages/ticketing/src/Actions/TransferPassToHolderAction.php:29` + `Services/DefaultPassTransferService.php:33` — Pre-saved holder + `pass_id` overwrite allows hijack/orphans**
Description: `resolveHolder` `save()`s a new `PassHolder(is_current=true)` before `transfer()` runs. If `transfer` then throws, orphan current holder remains (single-transfer path has no outer txn). If caller passes an existing `PassHolder` from another pass, `transfer` blindly does `$newHolder->pass_id=$pass->getKey()` and saves, stealing the holder row.
Evidence: `if($newHolder instanceof PassHolder) return $newHolder;` then in service `$newHolder->pass_id=$pass->getKey(); $newHolder->is_current=true; $newHolder->save();`.
Recommendation: Validate `$newHolder->pass_id===null||===$pass->id`, wrap resolve+transfer in one txn, add `selectForUpdate` on current holder, unique partial index `(pass_id) where is_current`.
Confidence: high.

**4. high/performance — `packages/ticketing/src/Services/DefaultPassIssuer.php:23` + `Listeners/IssuePassesOnOrderPaid.php:35` — Unbounded `quantity` issuance DoS**
Description: `issuePassesFor` loops `quantity` with no max; `IssuePassesOnOrderPaid` passes `$item->quantity` directly. Large quantity allocates `quantity` models + pass numbers in memory and inserts in one txn; `generatePassNumbers` has `while(true)` retry loops.
Evidence: `if($context->quantity<=0) return ...; $passNumbers=$this->generatePassNumbers($context->quantity); foreach...makePass` ; `quantity:$item->quantity`.
Recommendation: Cap quantity (e.g. config `max_issue_quantity`, default 100-500), chunk inserts, bound retry loops.
Confidence: high.

**5. medium/bug — `packages/ticketing/src/Services/DefaultPassIssuer.php:155` — Bulk `insert()` bypasses events; only first pass owner-guarded**
Description: `insertPasses` uses `Pass::query()->insert($passes->map->getAttributes())`, skipping `saving/creating` observers, audit, casts. Only `firstPass` is passed to `TicketingOwnerGuard::assertRelations`.
Evidence: `TicketingOwnerGuard::assertRelations($firstPass,...); $this->insertPasses($passes);` + `Pass::query()->insert(...)`.
Recommendation: Guard all (or assert single owner) and document event bypass; consider `createMany` or fire audit manually.
Confidence: high.

**6. medium/security — `packages/ticketing/src/Actions/IssuePassesAction.php:53` — Holder attributes mass-assigned without validation**
Description: `name/email/holder_type/holder_id` taken verbatim from `PassIssuanceContext` (which `IssuePassesOnOrderPaid` fills from cart `options['participants']`, user-controlled). No email format/length checks; arbitrary `holder_type` morph strings accepted (guard only checks existence/HasOwner).
Evidence: `$holder->name=$holderAttributes['name']??null; $holder->email=...; if(isset(...['holder_type'],...['holder_id'])){$holder->holder_type=...;}`.
Recommendation: Validate via FormRequest/Data rules (email, max lengths, allow-list morph types), or resolve holder models server-side.
Confidence: high.

**7. medium/bug+performance — `packages/ticketing/src/Jobs/BulkSendTransferNotificationsJob.php:41` — N+1 + wrong `previousHolder` in bulk emails**
Description: Loops `$event->passes` doing `$pass->holder` per pass (N+1). Event carries only `$newHolders->first()` as `toHolder`, then job does `$transferredFrom=$event->toHolder??$holder` and passes it as `previousHolder` to `PassTransferredToNewHolderNotification($pass,$transferredFrom)`, so every email shows the first new holder as “Transferred by”.
Evidence: `foreach($this->event->passes as $pass){ $holder=$pass->holder; $transferredFrom=$this->event->toHolder??$holder; notify(new ...( $pass,$transferredFrom,...));}`.
Recommendation: Eager-load holders, store per-pass previous-holder map in event, fix arg order.
Confidence: high.

**8. medium/bug — `packages/ticketing/src/Listeners/SendTransferNotifications.php:19` — No blank-email guard (vs delivery service has it)**
Description: Directly `Notification::route('mail',$previousHolder->email)->notify(...)` for both holders. If either email null/blank, `route('mail',null)` errors or mis-sends. `DefaultPassDeliveryService:22` correctly does `if(blank($holder->email)) return;`.
Recommendation: Mirror blank checks; skip or log.
Confidence: high.

**9. medium/security — `packages/ticketing/src/Actions/AddTicketTypeToCartAction.php:105` — `extraAttributes` can override system attrs; `participants` unbounded**
Description: `return array_merge($attributes,$extraAttributes)` lets caller override `purchasable_type/id, inventoryable_*, code`. `participants` merged without size/shape validation and persisted in cart.
Evidence: `$attributes=[purchasable_type...,participants...]; ... return array_merge($attributes,$extraAttributes);`.
Recommendation: Allow-list extra keys, reject collisions, cap participants count/size and validate emails.
Confidence: med.

**10. medium/performance — `packages/ticketing/src/Models/TicketType.php:294` — `getTotalAvailable()` loads all levels**
Description: `inventoryLevels()->get()->sum(fn=> $level->available)` hydrates all rows + accessor per row; called from cart validation (`hasInventory`) per add.
Evidence: `return (int)$this->inventoryLevels()->get()->sum(static fn(InventoryLevel $l)=> $l->available);`.
Recommendation: Aggregate in SQL (`sum(quantity_on_hand - reserved)` or scopes), add covering index.
Confidence: high.

**11. medium/bug — migrations missing uniqueness/indexes — `packages/ticketing/database/migrations/2000_01_01_000001_create_ticket_types_table.php:20`, `..._000002_:17`, `..._000004_:31`**
Description: `code` is `index()` not unique; `EnsureTicketTypeAction:19` uses `firstOrNew(ticketable+code)` so concurrent ensures duplicate. `ticket_type_components` has no composite unique `(parent,component)`. `passes.transfer_expires_at` (used by `ExpireTransfersCommand:26` range scan) has no index.
Recommendation: Add `unique(ticketable_type,ticketable_id,code)`, `unique(parent,component)`, `index(transfer_expires_at)`.
Confidence: med.

## pricing

**12. high/bug — `packages/pricing/database/migrations/2000_12_01_000002_create_prices_table.php:15`, `..._000001_:25`, `..._000003_:15` — `foreignUuid` creates DB FKs, violating “no FK” rule**
Description: `foreignUuid('price_list_id')`, `foreignUuid('customer_id')->nullable()`, etc. create real foreign-key constraints. Repo rule forbids DB FKs/cascades (ticketing correctly uses `uuid+index`).
Evidence: `$table->foreignUuid('price_list_id'); $table->foreignUuid('customer_id')->nullable();`.
Recommendation: Replace with `$table->uuid(...)->index()` (+ app-level existence checks already present).
Confidence: high.

**13. high/bug — `packages/pricing/src/Services/PriceCalculator.php:181` + `Support/CustomerPriceResolver.php:19`, `SegmentPriceResolver.php:19`, `TierResolver.php:15` — Currency ignored in resolution**
Description: `calculate` reads `$currency` from context/config but `getPriceListPrice`/customer/segment/tier queries never filter by `currency`. A list in another currency can win and its minor-unit amount is returned under the requested currency.
Evidence: `$currency=Arr::get($context,'currency')...;` then `PriceList::query()->default()->...->first()` and `Price::where(price_list_id,...)->first()` with no currency predicate.
Recommendation: Filter lists/prices/tiers by currency (or convert explicitly); add test with multi-currency lists.
Confidence: high.

**14. high/bug — `packages/pricing/src/Actions/ApplyPromotionalAdjustment.php:67` — Stub cart/item breaks promotion targeting**
Description: Builds anonymous cart/item where `getAttribute()` always returns null and items lack type/price/category. Real `PromotionService` targeting on product attributes will never match; `promotionable_type/id` only smuggled via `metadata` the service may ignore.
Evidence: `getAttribute(string $key):mixed{return null;}` + `TargetingContext(cart:$cart, metadata: [...promotionable_type...])`.
Recommendation: Pass real priceable/cart shape or extend promotion contract to accept explicit target; add integration test.
Confidence: med.

**15. medium/performance — `packages/pricing/src/Services/PriceCalculator.php:70` — 4–5 queries per `calculate`, no batch/cache**
Description: Every item triggers customer→segment→tier→promotion→pricelist queries + `PriceCalculated::dispatch`. Cart with N items = 5N queries, plus promotion service call. No memoization or `calculateMany`.
Recommendation: Add batch API, request-scoped memo for lists/tiers, optional cache with owner+effective_at key.
Confidence: high.

**16. low/performance — missing indexes for hot scopes — `packages/pricing/src/Models/Price.php:202`, `PriceList.php:146`, `PriceTier.php:23`**
Description: `scopeActive` filters `deactivated_at`, `scopeDefault` filters `is_default`, tier lookup filters `is_active`, none indexed (only `starts/ends`, `is_active+priority` exist).
Recommendation: Add `index(deactivated_at)`, `index(is_default)`, `index(is_active)` / composite with existing keys.
Confidence: med.

## filament-cashier

**17. high/bug — `packages/filament-cashier/src/Resources/UnifiedSubscriptionResource/Pages/ListSubscriptions.php:134` + `UnifiedInvoiceResource/Pages/ListInvoices.php:110` — Admin lists show only current user**
Description: Both `getAllSubscriptions/Invoices` branch on `auth()->user() instanceof BillableContract` then call `Cashier::gateway($g)->subscriptions/invoices($user)`. Admin sees own subscriptions, not owner-scoped all; tabs/badges/counts likewise wrong.
Evidence: `$user=auth()->user(); if(!$user instanceof BillableContract...) return collect(); ...Cashier::gateway($gateway)->subscriptions($user);`.
Recommendation: Query owner-scoped subscription/invoice models (like widgets do via `OwnerScopedQuery`) for admin; keep per-user query only for portal.
Confidence: high.

**18. medium/performance — `packages/filament-cashier/src/CustomerPortal/Pages/ManageSubscriptions.php:71` + `Support/CustomerSubscriptionsQuery.php:31` — Unbounded `loadMore`**
Description: `loadMoreSubscriptions(int $increment=50)` does `$this->perGatewayLimit+=max(1,$increment)` with Livewire-exposed int, no max. Each render fetches `limit+1` per gateway and sorts in PHP.
Evidence: `public function loadMoreSubscriptions(int $increment=self::DEFAULT_LOAD_MORE_INCREMENT):void{ $this->perGatewayLimit+=max(1,$increment);}`.
Recommendation: Clamp increment/total (e.g. max 200), validate, paginate server-side.
Confidence: high.

**19. medium/security — `packages/filament-cashier/src/Widgets/TotalMrrWidget.php:69`, `TotalSubscribersWidget.php:29`, `GatewayBreakdownWidget.php:87`, `GatewayComparisonWidget.php:35` — `once()` without owner key**
Description: All use global `once(fn)` with no key. Within one request/job with owner switching (Octane, jobs, multi-panel), second owner gets first owner’s cached totals. Also hides per-user differences.
Evidence: `return once(function():array{ $detector=app(GatewayDetector::class); ... OwnerScopedQuery::apply(...)->chunk...});`.
Recommendation: Key by owner (`once(fn, OwnerContext::cacheKey())` / `OwnerCache`) or drop `once` in favor of property memo.
Confidence: med.

**20. medium/bug — `packages/filament-cashier/src/Widgets/TotalMrrWidget.php:36` — Currency conversion inverted for non-USD base**
Description: `if(display_converted){ foreach... if($currency!==$base && isset($rates[$currency])) $primaryMrr+=(int)($amount/$rates[$currency]);}`. With config `MYR=>4.70,USD=>1.00` and base `MYR`, USD amount divides by 1 (no conversion); correct is `*4.7`. Only correct when base is USD.
Recommendation: Store rates vs base consistently (`amount * rate[target]/rate[source]`) and test both bases.
Confidence: med.

**21. high/performance — `packages/filament-cashier/src/Widgets/GatewayComparisonWidget.php:98` — 12 full chunk scans per render**
Description: `getMonthlyDataForGateway` loops 6 months × gateways, each doing `OwnerScopedQuery->with('items')->whereBetween(...)->chunk(200)` + `UnifiedSubscription::from*` per row. Dashboard render = up to 12 table scans.
Recommendation: Single grouped query per gateway (or pre-aggregated MRR table), cache 5–15 min.
Confidence: high.

**22. medium/security+performance — `packages/filament-cashier/src/Pages/GatewayManagement.php:67` — Live gateway probes on every render + leaky errors + global Stripe key mutation**
Description: `getGatewayHealth()` maps `availableGateways()` → `checkStripeHealth` (`Stripe::setApiKey($secret); Account::retrieve(); finally restore`) and `checkChipHealth` (`getAccountBalance()`) synchronously, no cache. Failures return `$e->getMessage()` to UI (can leak SDK/config details). Mutating global `Stripe::setApiKey` is Octane-unsafe.
Evidence: `Stripe::setApiKey($secret); try{Account::retrieve();}finally{Stripe::setApiKey(...);}` + `catch(Exception $e){return [...'message'=>$e->getMessage()];}`.
Recommendation: Cache health 60s+, generic user message + log detail, use per-request Stripe client instead of global.
Confidence: high.

**23. medium/performance — `packages/filament-cashier/src/CustomerPortal/Pages/ViewInvoices.php:41` — Unbounded invoice fetch**
Description: Iterates all `$user->invoices()` / `chipInvoices()` with no limit/pagination, sorts in PHP. Heavy users stall portal.
Recommendation: Paginate/limit (e.g. 50 + load-more), cache per user.
Confidence: med.

**24. medium/security — `packages/filament-cashier/src/Resources/UnifiedInvoiceResource/Tables/InvoicesTable.php:94` — Export bulk action has no authz; fragile date assumption**
Description: `BulkAction export` streams CSV for any selected `UnifiedInvoice`s with no `SubscriptionPolicy`-style ownership check (unlike subscription bulk cancel). Also `$invoice->date->format()` assumes non-null Carbon.
Evidence: `BulkAction::make('export')->action(fn(Collection $records):StreamedResponse=>... $invoice->date->format('Y-m-d')...)` with no policy call.
Recommendation: Authorize each invoice (billable match), null-guard date, escape CSV formula prefixes.
Confidence: med.

## filament-chip

**25. high/performance — `packages/filament-chip/src/Widgets/ChipStatsWidget.php:72`, `PaymentMethodsWidget.php:50`, `TokenStatsWidget.php:50`, `RevenueChartWidget.php:67` — Unbounded `->get()` + PHP sums**
Description: Revenue/breakdown widgets do `Purchase::query()->forOwner()->where(...)->get()` then `sum($purchase->purchase['total'])` per period. `ChipStats` runs 3 such scans + 2 counts per render; `PaymentMethods` scans all paid purchases; 30-day chart loads all rows. Memory grows with volume.
Evidence: `$purchases=$query->get(); return $purchases->sum(fn(Purchase $p)=>(int)($p->purchase['total']??0));`.
Recommendation: Aggregate in SQL (JSON extracts per driver like `PurchaseTable:131` does), add date/status indexes, cache 60–300s.
Confidence: high.

**26. medium/security — `packages/filament-chip/src/Pages/AnalyticsDashboardPage.php:16` — Livewire `period` injection**
Description: `public string $period='30'` + `updatedPeriod()->loadMetrics()` with `$startDate=$endDate->subDays((int)$this->period)` and no validation. User can set `999999`/negative via Livewire, causing huge `getDashboardMetrics/getRevenueTrend` scans.
Evidence: `public string $period='30'; ... $startDate=$endDate->subDays((int)$this->period);`.
Recommendation: Validate `in:7,30,90` (or int 1..365), cast + clamp.
Confidence: high.

**27. medium/performance — `packages/filament-chip/src/Resources/BaseChipResource.php:39` — Badge `count()` per resource per nav render**
Description: `getNavigationBadge()` does `(int)static::getEloquentQuery()->count()` for each of 6 resources on every page load, no cache.
Recommendation: Cache counts 30–60s per owner or disable badges.
Confidence: high.

**28. medium/bug — `packages/filament-chip/src/Actions/PurchaseExporter.php:45` — `bool` type-hint crashes on int; sensitive `checkout_url` exported**
Description: `->formatStateUsing(fn(bool $state):string=>...)` with `strict_types=1` TypeErrors when DB returns `0/1`/null for `is_test`. Export also includes `checkout_url` (signed payment URL) + `client_id` without redaction.
Evidence: `ExportColumn::make('is_test')->formatStateUsing(fn(bool $state):string=> $state?'Yes':'No')` + `ExportColumn::make('checkout_url')`.
Recommendation: Accept `mixed` and cast, drop/redact `checkout_url`, scope export query by owner.
Confidence: med.

**29. medium/security — `packages/filament-chip/src/Widgets/ChipStatsWidget.php:128`, `RevenueChartWidget.php:121`, `RecentTransactionsWidget.php:91` — Silent global fallback when no owner**
Description: `if(resolve()!==null||isExplicitGlobal()) return $cb(); return withOwner(null, $cb);` renders cross-owner revenue/transactions for any Filament user with no owner context, without explicit-global permission check.
Recommendation: Require `OwnerContext::isExplicitGlobal()` + capability; otherwise return empty/unauthorized.
Confidence: med.

**30. low/performance — `packages/filament-chip/src/Resources/ClientResource.php:124`, `PaymentResource.php:116` — Distinct filter options uncached**
Description: Country/currency `SelectFilter::options(fn()=> Client/Payment::query()->forOwner()->distinct()->pluck...)` runs on every table render.
Recommendation: Cache 5–15 min per owner.
Confidence: high.

### Prior-audit chunk: filament-cashier

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-cashier` MEDIUM — no resource `getEloquentQuery`; `InvoicesTable:79-84` external `pdfUrl` action IDOR if list unscoped. Add resource-level query.
- `filament-cashier-chip` MEDIUM perf — inheritance contract GOOD (`BaseCashierChipResource` + `assertResolvedOrExplicitGlobal`). `CustomerPortal/Invoices:59-73` safe iff `getBillable()` owner-bound — verify else HIGH.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-cashier

- [R1:#17] CONFIRMED high bug | filament-cashier/.../ListSubscriptions.php:134 | "Admin lists show": per-user gateway queries; admin sees own subs/invoices only
- [R1:#18] CONFIRMED medium perf | filament-cashier/.../ManageSubscriptions.php:71 | "Unbounded loadMore increment": Livewire int unclamped; limit+1 per gateway per render
- [R1:#19] CONFIRMED medium security | filament-cashier/src/Widgets/TotalMrrWidget.php:69 | "once without owner": 4 widgets use unkeyed once(); owner-switch leaks totals
- [R1:#20] CONFIRMED medium bug | filament-cashier/src/Widgets/TotalMrrWidget.php:36 | "Currency conversion inverted": divides by source rate; wrong unless base=USD; shipped base is MYR
- [R1:#21] CONFIRMED high perf | filament-cashier/src/Widgets/GatewayComparisonWidget.php:98 | "12 full chunk": 6mo x gateways chunk scans + per-row hydration per render
- [R1:#22] CONFIRMED medium sec+perf | filament-cashier/src/Pages/GatewayManagement.php:67 | "Live gateway probes": sync probes uncached; raw exception msg to UI; global Stripe key mutation
- [R1:#23] CONFIRMED medium perf | filament-cashier/.../ViewInvoices.php:41 | "Unbounded invoice fetch": all invoices fetched, PHP-sorted, no limit
- [R1:#24] DOWNGRADED (was medium) low security | filament-cashier/.../InvoicesTable.php:94 | "Export bulk action": date non-nullable (UnifiedInvoice:23); no per-record authz but per-user scoped
- [AUD:B1] ADOPTED medium security | filament-cashier/.../InvoicesTable.php:79 | no getEloquentQuery in either resource; pdfUrl IDOR-if-unscoped stands
- [AUD:B2] ADOPTED low perf | filament-cashier-chip/.../Invoices.php:59 | getBillable auth-user-bound so conditional HIGH cleared; contract GOOD
- [AUD:B3] ADOPTED pass info | audits/vpkg/audit-filament-cashier.md:3 | G1 Navigation PASS; no finding for this package
- [AUD:B4] FALSE medium perf | filament-cashier/.../UnifiedSubscriptionResource.php:67 | G2 badge COUNT: cashier badges return null/absent, no uncached count
- [AUD:B5] ADOPTED medium perf | audits/vpkg/audit-filament-cashier.md:5 | G3 TextColumn N+1 names affiliates/events only; no cashier claim
- [AUD:B6] ADOPTED medium perf | audits/vpkg/audit-filament-cashier.md:6 | G4 pluck()+preload clusters elsewhere; none in cashier Resources
- [AUD:B7] ADOPTED low perf | audits/vpkg/audit-filament-cashier.md:7 | G5 domain leakage names vouchers only; no cashier claim
- [AUD:B8] ADOPTED medium perf | audits/vpkg/audit-filament-cashier.md:8 | G6 sums: cashier-chip now withSum per FALSE-list; residual counts only

### Prior-audit chunk: ticketing

### Prior-audit section
### ticketing
Bugs:
- `Pass:85-94`, `TicketType:79-86` HIGH — no `deleting` cascades; orphans holders/transfers/allocations/components.
- `TicketType status` raw string MEDIUM — no enum cast (Pass uses `PassState`).
- `DefaultPassIssuer:131-132` LOW-MEDIUM (was MEDIUM) — retry only `pass_no`; `qr/barcode` collision negligible but unhandled.
- `DefaultPassTransferService:24-47` HIGH — txn but no `lockForUpdate`, no same-pass/owner check → double-transfer.
Security:
- `TransferPassToHolderAction:38-64`, `IssuePassesAction:53-70` HIGH — arbitrary `holder_type/id` morph, no allowlist/owner check.
- `TicketingOwnerGuard` skips non-`HasOwner` LOW-MEDIUM — by-design for global models.
Performance:
- `TicketType:294-303` MEDIUM (was HIGH) — `getTotalAvailable` hydrates `inventoryLevels()->get()->sum()`; `getTotalOnHand` already SQL. Fix with `SUM(GREATEST(...))`.
- Bulk transfer nested txns + 2 writes/pass MEDIUM — chunk or queue for 100s.

### Prior-audit fix-first rows
| 18 | ticketing | `Actions/TransferPassToHolderAction`, `IssuePassesAction` | Arbitrary `holder_type/id` morph, no owner check | HIGH |
| 19 | ticketing | `Services/DefaultPassTransferService.php:24-47` | No lock, no same-pass/owner check → double-spend | HIGH |
| — | ticketing | `Pass:85-94`, `TicketType:79-86` | No deleting cascades → orphans | HIGH |

#### Verdicts: ticketing

- [R1:#1] CONFIRMED critical security | ticketing/src/Actions/TransferPassToHolderAction.php:17 | "Transfer actions never": no Gate/policy call in actions/service; policy registered but unenforced
- [R1:#2] CONFIRMED high security | ticketing/src/Actions/BulkTransferPassesAction.php:58 | "Bulk event/job uses": job context from first pass only; mixed-owner batch not rejected
- [R1:#3] CONFIRMED high bug | ticketing/src/Actions/TransferPassToHolderAction.php:29 | "Pre-saved holder +": holder saved pre-txn w/o outer txn; pass_id overwritten blindly; DUP AUD:Q19 partial
- [R1:#4] CONFIRMED high perf | ticketing/src/Services/DefaultPassIssuer.php:23 | "Unbounded quantity issuance": no quantity cap in code/config; while(true) retries; raw qty from listener
- [R1:#5] CONFIRMED medium bug | ticketing/src/Services/DefaultPassIssuer.php:155 | "Bulk insert bypasses": insert() skips model events; only firstPass owner-guarded
- [R1:#6] CONFIRMED high security | ticketing/src/Actions/IssuePassesAction.php:53 | "Holder attributes mass-assigned": no email/morph validation; DUP AUD:Q18, severity adopted HIGH
- [R1:#7] CONFIRMED medium bug+perf | ticketing/src/Jobs/BulkSendTransferNotificationsJob.php:41 | "N+1 + wrong": per-pass holder query; first NEW holder passed as previousHolder
- [R1:#8] CONFIRMED medium bug | ticketing/src/Listeners/SendTransferNotifications.php:19 | "No blank-email guard": route('mail',email) unguarded for both holders
- [R1:#9] CONFIRMED medium security | ticketing/src/Actions/AddTicketTypeToCartAction.php:105 | "extraAttributes can override": array_merge overrides system keys; participants unbounded
- [R1:#10] CONFIRMED medium perf | ticketing/src/Models/TicketType.php:294 | "getTotalAvailable loads all": get()->sum hydration per call; DUP AUD:B7; FALSE-list caps at MEDIUM
- [R1:#11] CONFIRMED medium bug | ticketing/database/migrations/2000_01_01_000001_create_ticket_types_table.php:20 | "migrations missing uniqueness/indexes": code non-unique; no composite unique; transfer_expires_at unindexed
- [AUD:Q18] ADOPTED high security | ticketing/src/Actions/TransferPassToHolderAction.php:38 | DUP R1:#6 merged: arbitrary holder_type/id morph, no allowlist
- [AUD:Q19] ADOPTED high bug | ticketing/src/Services/DefaultPassTransferService.php:24 | no lockForUpdate/same-pass check; DUP R1:#3 partial
- [AUD:QNN] ADOPTED high bug | ticketing/src/Models/Pass.php:85 | unnumbered queue row: no deleting cascades; DUP AUD:B1
- [AUD:B1] ADOPTED high bug | ticketing/src/Models/Pass.php:85 | DUP AUD:QNN: only saving hooks in Pass/TicketType, orphans on delete
- [AUD:B2] ADOPTED medium bug | ticketing/src/Models/TicketType.php:123 | status has no enum cast (Pass uses PassState)
- [AUD:B3] ADOPTED low-medium bug | ticketing/src/Services/DefaultPassIssuer.php:131 | retry regenerates pass_no only; qr/barcode collision unhandled
- [AUD:B4] ADOPTED high bug | ticketing/src/Services/DefaultPassTransferService.php:24 | DUP AUD:Q19
- [AUD:B5] ADOPTED high security | ticketing/src/Actions/IssuePassesAction.php:53 | DUP AUD:Q18/R1:#6
- [AUD:B6] ADOPTED low-medium security | ticketing/src/Support/TicketingOwnerGuard.php:81 | skips non-HasOwner by design; code as described
- [AUD:B7] ADOPTED medium perf | ticketing/src/Models/TicketType.php:294 | DUP R1:#10
- [AUD:B8] ADOPTED medium perf | ticketing/src/Actions/BulkTransferPassesAction.php:48 | outer txn + per-pass txn, 2 writes/pass

### Prior-audit chunk: pricing

### Prior-audit section
### pricing
Bugs:
- Dual `is_active` + `deactivated_at` drift LOW — no single transition helper.
- No `amount>=0 / min_quantity>=1 / starts<=ends` validation MEDIUM.
Security: clean — owner auto-assign + cross-owner throw + `customer_id/segment_id` scoped `exists()`.
Performance: `PriceCalculator:86-205` MEDIUM — customer→segment→tier→promo→list fan-out (~100+ queries on 50-line cart, directionally); no batch preload. `clearOtherDefaults:342` bulk `update` GOOD.

#### Verdicts: pricing

- [R1:#12] CONFIRMED high bug | pricing/database/migrations/2000_12_01_000002_create_prices_table.php:15 | "foreignUuid creates DB": 4 foreignUuid across 3 migrations create real FKs
- [R1:#13] CONFIRMED high bug | pricing/src/Services/PriceCalculator.php:181 | "Currency ignored in": resolvers never filter currency though Price/PriceTier carry it
- [R1:#14] DOWNGRADED (was high) medium bug | pricing/src/Actions/ApplyPromotionalAdjustment.php:67 | "Stub cart/item breaks": product-id rules match via $item->id; attr/category rules never match
- [R1:#15] CONFIRMED medium perf | pricing/src/Services/PriceCalculator.php:70 | "4-5 queries per": per-item resolver fan-out, no batch/cache; DUP AUD:B3
- [R1:#16] CONFIRMED low perf | pricing/src/Models/Price.php:202 | "missing indexes for": deactivated_at/is_default/is_active unindexed
- [AUD:B1] ADOPTED low bug | pricing/src/Models/PriceList.php:128 | dual is_active+deactivated_at, no single transition helper
- [AUD:B2] ADOPTED medium bug | pricing/src/Models/Price.php:274 | no amount>=0/min>=1/starts<=ends validation located
- [AUD:B3] ADOPTED medium perf | pricing/src/Services/PriceCalculator.php:86 | DUP R1:#15; fan-out direction kept per FALSE-list

### Prior-audit chunk: filament-chip

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-chip` MEDIUM — `BaseChipResource:17-27` silent unscoped fallback (should `whereRaw('0=1')` like shipping). Statement `redirect()->away(download_url)` no action-level re-validation MEDIUM.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-chip

- [R1:#25] CONFIRMED high perf | filament-chip/src/Widgets/ChipStatsWidget.php:72 | "Unbounded get +": get()+PHP sums across widgets; 3 scans+2 counts per render
- [R1:#26] CONFIRMED medium security | filament-chip/src/Pages/AnalyticsDashboardPage.php:16 | "Livewire period injection": period unvalidated; arbitrary subDays scan range
- [R1:#27] CONFIRMED medium perf | filament-chip/src/Resources/BaseChipResource.php:39 | "Badge count per": count() per resource per nav render, uncached; DUP AUD:B4
- [R1:#28] DOWNGRADED (was medium) low bug | filament-chip/src/Actions/PurchaseExporter.php:45 | "bool type-hint crashes": is_test boolean-cast so no crash; checkout_url still exported raw
- [R1:#29] CONFIRMED medium security | filament-chip/src/Widgets/ChipStatsWidget.php:128 | "Silent global fallback": withOwner(null) renders cross-owner data w/o capability check
- [R1:#30] CONFIRMED low perf | filament-chip/src/Resources/ClientResource.php:124 | "Distinct filter options": distinct pluck per table render, uncached
- [AUD:B1] ADOPTED medium security | filament-chip/src/Resources/BaseChipResource.php:17 | bare-query fallback w/o scopeForOwner; all chip models have it, latent
- [AUD:B2] ADOPTED medium security | filament-chip/.../ViewCompanyStatement.php:50 | redirect()->away(download_url) w/o action-level owner re-validation
- [AUD:B3] ADOPTED pass info | audits/vpkg/audit-filament-chip.md:3 | G1 Navigation PASS; no finding for this package
- [AUD:B4] ADOPTED medium perf | filament-chip/src/Resources/BaseChipResource.php:39 | G2 badge COUNT; DUP R1:#27
- [AUD:B5] ADOPTED medium perf | audits/vpkg/audit-filament-chip.md:5 | G3 N+1: no chip claim (affiliates/events only)
- [AUD:B6] ADOPTED medium perf | audits/vpkg/audit-filament-chip.md:6 | G4 pluck preload: no chip occurrence; clusters elsewhere
- [AUD:B7] ADOPTED low perf | audits/vpkg/audit-filament-chip.md:7 | G5 domain leakage: no chip claim
- [AUD:B8] ADOPTED medium perf | filament-chip/src/Widgets/ChipStatsWidget.php:72 | G6 sums in chip widgets; DUP R1:#25

---

## Round 2 — `filament-feedback`, `signals`, `filament-shipping`, `tax`

### E2E findings (verbatim)

End-to-end review of filament-feedback, signals, filament-shipping, tax. 27 findings (3 high-bug/security, 3 high-perf, 7 medium, 14 low). No FK constraints/cascades, no SoftDeletes found (verified by migration search); uuid PKs universal; money is minor-units in domain code.

== SIGNALS ==
S1 | high | security | packages/signals/src/Actions/IdentifySignalIdentity.php:62-71,150-155 | Public identify endpoint accepts spoofed auth linkage. `asController` validates `auth_user_type/id` as plain nullable strings, and `resolveAuthUser()` prioritizes payload values over the server-side authenticated user with no morph-map allowlist or ownership check. Any anonymous caller with a write_key can forge `auth_user_*` on identities. Evidence: `if (($payload['auth_user_type'] ?? null) !== null ...) return [(string) $payload[...]]` before the `auth_tracking` gate. Fix: remove these fields from public validation; derive linkage only from `auth()->user()` when `auth_tracking` is enabled. Confidence: high.
S2 | medium | security | packages/signals/src/Actions/IdentifySignalIdentity.php:33-46 | Identity `traits` persisted unfiltered, bypassing the property allowlist/blocklist privacy control. `handle()` stores `$payload['traits']` raw while event `properties` go through `filterProperties()` (allowlist + PII blocklist). Public `/collect/identify` can persist email/phone/PII into `traits`. Fix: apply the same allowlist+blocklist filter and depth/size caps to traits. Confidence: high.
S3 | high | performance | packages/signals/src/Services/SignalAlertEvaluator.php:128-136 | `filteredEvents()` loads the entire timeframe window with `->get()` then filters in PHP. Runs per rule on every ingest evaluation (IngestSignalEvent.php:202-245) and per rule in the scheduled command — OOM risk on high-volume properties. Fix: compile property conditions to SQL (reuse SignalCondition JSON expressions) with DB-side counts. Confidence: high.
S4 | high | performance | packages/signals/src/Services/ConversionFunnelReportService.php:166-179 | `calculateStageProgress()` does unbounded `->get()` of all matching events then `groupBy` in PHP per report render. Fix: DB-side per-stage aggregation or chunked streaming with mandatory date bounds + row caps. Confidence: high.
S5 | high | performance | packages/signals/src/Services/RetentionReportService.php:100-132 | `cohorts()` loads ALL identities via `->get()` then groups/filters in PHP. Same fix as S4. Confidence: high.
S6 | medium | bug | packages/signals/src/Actions/ResolveSession.php:106-115 | Duplicate session-identifier race only handles Postgres `23505`; MySQL (`23000`/1062) and SQLite violations are rethrown instead of re-fetching the row. Fix: driver-agnostic duplicate detection. Confidence: high.
S7 | medium | security | packages/signals/src/Actions/ResolveSession.php:183-233 | `CF-Connecting-IP` / `CF-IPCountry` headers trusted from any client and persisted as `ip_address`/`country_code`. Stored geo/IP is spoofable unless behind verified Cloudflare/proxy ranges. Fix: honor only under trusted-proxy config. Confidence: high (spoofability), med (exploit impact: analytics poisoning).
S8 | low | bug | packages/signals/src/Services/SignalsIngestionRequestValidator.php:105-113 | Byte cap uses `mb_strlen($request->getContent())` (characters, not bytes); multibyte payloads bypass `max_bytes`. Fix: `strlen()`. Confidence: high.
S9 | low | bug | packages/signals/src/Actions/IdentifySignalIdentity.php:126-131 | `resolveSeenAt()` calls `CarbonImmutable::parse()` with no try/catch (sibling `ResolveSession::parseTimestamp` catches `InvalidFormatException`). Invalid `seen_at` via programmatic `handle()` → unhandled 500. Controller path is safe (`date` rule). Fix: mirror the try/catch fallback. Confidence: high.
S10 | low | security | packages/signals/src/Services/SignalsDashboardService.php:117-124 | `withResolvedOwnerOrExplicitGlobal()` silently wraps ownerless calls in `withOwner(null)`, aggregating across all tenants — contrast TaxOwnerScope which fails closed via `assertResolvedOrExplicitGlobal`. Fix: throw or return empty unless explicit global. Confidence: med (depends on OwnerContext::withOwner(null) semantics, not inspected).
S11 | low | performance | packages/signals/src/Actions/IngestSignalEvent.php:87-98 | Every ingested event triggers a session `exists()` check plus a full session `save()` (2 writes + 1 read on the hot path). Fix: defer bounce/exit computation or batch. Confidence: med.
S12 | low | performance | packages/signals/src/Models/TrackedProperty.php:187-211 | `deleting` cascade does `Schema::hasTable()` per delete and `->get()->each(delete)` over growth experiments (unbounded, N+1 deletes). Fix: chunk deletes; cache schema check. Confidence: high.
S13 | low | security | packages/signals/config/signals.php:117 | `property_allowlist` permits `cookie_value`; storing raw cookie values in event properties risks persisting session material into analytics. Fix: remove unless explicitly needed. Confidence: med.
Signals positives: HMAC signature check with `hash_equals` + replay window + replay dedupe (VerifyTrustedSignalSignature.php); domain matching correctly documented as non-auth; browser event allowlist + normalized forbidden-field scan; payload depth/key/string caps; webhook/Slack delivery via PublicHttpUrlGuard+PinnedHttpClient (SSRF-safe); SignalCondition allowlisted fields/operators, parameterized bindings, fail-closed; owner-scoped jobs with mismatch detection; tracker renderer escapes attributes; migrations indexed, no FK/soft-deletes.

== TAX ==
T1 | high | bug/security | packages/tax/src/Actions/Exemption/RequestTaxExemption.php:15-23 | Unfiltered mass assignment: `new TaxExemption($attributes)` with caller-controlled `$attributes`; fillable includes `owner_type/id`, `status`, `verified_at`, `certificate_number`. Callers can forge `status=Approved` + `verified_at`, bypassing the Pending→review workflow (owner fields are only contained when owner scoping is enabled). Fix: explicit allowlist (exemptable_*, tax_zone_id, reason, certificate_number, document_path, dates) and force `PendingState`. Confidence: high.
T2 | medium | bug | packages/tax/src/Services/ZoneResolver/CompositeZoneResolver.php:48-55 | `clearCache()` clears child resolvers but not `$defaultResolver`, so default/fallback zone stays stale after `TaxZone::saved/deleted` within the request lifetime. Fix: also clear `$this->defaultResolver`. Confidence: high.
T3 | medium | bug/performance | packages/tax/src/Models/TaxZone.php:151-170 + packages/tax/src/Services/ZoneResolver/AddressZoneResolver.php:103-111 | `scopeForAddress()` filters countries/states but never postcodes; the resolver compensates by `->get()`-ing all country-matching zones and PHP-filtering via `matchesAddress()`. Standalone scope users get postcode-ignoring matches; resolver path loads unbounded zone sets. Fix: add postcode prefilter or document scope as candidate-only. Confidence: high (perf), med (correctness, primary caller compensates).
T4 | low | bug | packages/tax/src/Services/TaxCalculator.php:224-227 + packages/tax/src/Data/TaxResultData.php:69-74 | `context['currency']` flows unvalidated into results and into dynamic `Money::{$currency}()` static dispatch. Fix: ISO-code allowlist, fallback to default. Confidence: med.
T5 | low | bug | packages/tax/src/Console/Commands/RecalculateTaxRatesCommand.php:30-49; SyncTaxZonesCommand.php:29-42 | Both commands count rows then print "complete" without performing any work — misleading ops surface. Fix: implement or remove. Confidence: high.
T6 | low | bug | packages/tax/src/Models/TaxZone.php:353-384 | `matchesPostcode()` range mode strips non-digits then compares numerically, so alphanumeric postcodes (UK/CA) collapse and false-match (e.g. "AB12"→12). Fix: numeric fast-path only when both sides are numeric; otherwise exact/wildcard. Confidence: med.
Tax positives: owner write guards on all four models incl. exemptable + zone cross-scope checks; owner-scoped ZoneIdResolver (no IDOR); minor-units money + basis-point rates; exemption state machine; TaxOwnerScope fail-closed; commands use OwnerBatchRunner.

== FILAMENT-FEEDBACK ==
F1 | high | bug | FeedbackFormAnalytics.php:14-17; ManageFeedbackFormBuilder.php:14-17; FeedbackLatestCommentsWidget.php:12-15; FilamentFeedbackServiceProvider.php:12-17 | Three views referenced (`filament-feedback::pages.*`, `::widgets.*`) but the package has no `resources/views` directory and the provider registers no views (`hasConfigFile()` only) — these pages/widget 500. Caveat: assumes no other package registers the `filament-feedback::` namespace (none found in-package). Fix: add views or remove the pages/widget. Confidence: high.
F2 | low | performance | all 9 src/Widgets/*.php + Pages/FeedbackDashboard.php:35-48 | Each widget calls `FeedbackAnalyticsService::dashboard()` independently (9×/render). Mitigated: `dashboard()` is wrapped in `OwnerCache::remember` (30s TTL, packages/feedback/src/Analytics/FeedbackAnalyticsService.php:93-139, verified). Residual: cold-start full recompute + possible stampede + fail-closed throw without owner context. Fix: single memoized call per request. Confidence: high (redundant calls), med (stampede — OwnerCache internals not inspected).
F3 | low | performance | FeedbackFormResource.php:42-46,67-69 | `getEloquentQuery()` adds `withCount('responses')` and the table column also uses `->counts('responses')` — duplicate counting. Fix: keep one. Confidence: med.
F4 | low | security | Exports/FeedbackResponsesExport.php:19-38 | Export exposes `ip_address` + subject/respondent identifiers (IP hidden by default but exportable). Ensure export actions carry strict authorization; consider dropping IP. Confidence: high (exposure), med (auth gap — relies on Filament default export authz).
Feedback positives: OwnerUiScope on all 5 resources + all 3 exporters; navigation group via config + getNavigationGroup; orchestration delegated to domain actions; no raw HTML.

== FILAMENT-SHIPPING ==
H1 | high | bug | ShippingRateForm.php:74,80,87,93,100,117,172; RatesRelationManager.php:65,71,78,84,91,108,161; ShipmentForm.php:84,90 | Major→minor money conversion uses `(int)($state*100)` / `$state*100` with no rounding — float truncation (e.g. 19.99→1998). The weight path in the same codebase correctly uses `(int) round($state*1000)` (ShipmentForm.php:78), proving the inconsistency. Fix: `(int) round($state*100)` or MoneyNormalizer. Confidence: high.
H2 | medium | performance | Support/ShippingStatsAggregator.php:81-91 | `getAllStats()` recomputes all four counts to build `total`, then recomputes each again — 9 count queries per call. Fix: compute once, sum in PHP. Confidence: high.
H3 | medium | bug/performance | Pages/ManifestPage.php:223-251 | `mark_all_picked_up` does unbounded `->get()` then one `update()` per shipment (memory + N+1 writes, no chunking). Fix: `chunkById` or single mass update. Confidence: high.
H4 | low | security | Pages/FulfillmentQueue.php:132-143 vs :83-87,110-114 | Badge methods return null when owner scoping is enabled but no owner is resolved; `table()` has no such guard and queries with a null owner — behavior depends on unverified `forOwner(null)` semantics. Fix: mirror the guard. Confidence: med.
H5 | low | security | Actions/PrintLabelAction.php:207-210 | Bulk label errors interpolate `$e->getMessage()` into user notifications (single-label path correctly uses a generic message). Fix: generic message + log. Confidence: high.
Shipping positives: consistent owner scoping with fail-closed `whereRaw('0 = 1')`; per-record `authorize`/`can` checks in row and bulk actions; label URL scheme allowlist + temporary signed label URLs with short-lived cache; parameterized selectRaw bindings (CarrierPerformanceWidget); BatchRateLimiter for carrier ops; navigation group via config.

Unresolved/limits: commerce-support internals (OwnerCache stampede behavior, forOwner(null) semantics); `shipping.labels.show` route validation (shipping package out of scope); per-goal report query shapes beyond files read.

### Prior-audit chunk: filament-feedback

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-feedback` GOOD — all 5 scoped, exports scoped.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-feedback

- [F1] CONFIRMED high bug | packages/filament-feedback/src/Resources/FeedbackFormResource/Pages/FeedbackFormAnalytics.php:14-17 | 3 getView refs to filament-feedback::pages/widgets; no resources/views, no hasViews/loadViewsFrom; pages 500.
- [F2] CONFIRMED low perf | packages/filament-feedback/src/Widgets/FeedbackLatestCommentsWidget.php:19 | All 9 widgets call dashboard() per render; OwnerCache 30s mitigates; cold-start recompute + stampede remain.
- [F3] CONFIRMED low perf | packages/filament-feedback/src/Resources/FeedbackFormResource.php:42-46 | getEloquentQuery withCount('responses') plus column counts('responses'): redundant double count.
- [F4] CONFIRMED low security | packages/filament-feedback/src/Exports/FeedbackResponsesExport.php:19-38 | Exports subject/respondent ids + ip_address (hidden default); no explicit export authz; Filament default only.
- [AUD:B1] ADOPTED info note | packages/filament-feedback/src/Resources | GOOD: 5 resources + 3 exporters owner-scoped; no action.
- [AUD:B2] ADOPTED info note | packages/filament-feedback/src/Resources/FeedbackFormResource.php:25-35 | G1 PASS: getNavigationGroup via config, no static group; sort keys compliant.
- [AUD:B3] ADOPTED medium perf | packages/filament-feedback/src | G2: no getNavigationBadge in pkg; global uncached-COUNT MEDIUM stands for other pkgs.
- [AUD:B4] ADOPTED medium perf | packages/filament-feedback/src/Resources/FeedbackResponseResource.php:52 | G3: form.name columns w/o with() in Invitation/Response; OwnerUiScope only; N+1 in pkg.
- [AUD:B5] ADOPTED medium perf | packages/filament-feedback/src | G4: no options(pluck)+preload in pkg; global whole-table pattern stands elsewhere.
- [AUD:B6] ADOPTED medium bug | packages/filament-feedback/src | G5: no in-pkg domain-leak citation; global LOW/MEDIUM note stands.
- [AUD:B7] ADOPTED medium perf | packages/filament-feedback/src | G6: no get()->sum in pkg; global uncached-count residual stands.

### Prior-audit chunk: signals

### Prior-audit section
### signals
Bugs:
- `IngestSignalEvent:260-262` MEDIUM-HIGH privacy — `'*'` allowlist skips PII blocklist.
- Browser events no idempotency MEDIUM — `idempotencyKey` trusted-only `:47-57`; retries duplicate rows.
- `revenue_minor (int)` cast LOW — truncates floats, accepts negatives.
Security:
- `write_key random(40)` bearer in query LOW — plaintext; sent as `data-write-key` + body/query → log leak. Prefer header/body.
- Trusted/browser ingestion GOOD — strict timestamp/replay/format/`hash_equals`/RateLimiter dedup; per-prop/IP limits.
Performance: GOOD — async geocode default; queued alert eval; reports eager-load; `(tracked_property,idempotency_key)` unique.

#### Verdicts: signals

- [S1] CONFIRMED high security | packages/signals/src/Actions/IdentifySignalIdentity.php:62-71 | Public validate allows auth_user_* strings; resolveAuthUser prefers payload over auth()->user; no morph allowlist.
- [S2] CONFIRMED medium security | packages/signals/src/Actions/IdentifySignalIdentity.php:33 | traits stored raw, no filterProperties allowlist/blocklist; PII into traits via /collect/identify.
- [S3] CONFIRMED high perf | packages/signals/src/Services/SignalAlertEvaluator.php:128-136 | filteredEvents get()+PHP filter per rule; DB fast-path only when no property filters.
- [S4] CONFIRMED high perf | packages/signals/src/Services/ConversionFunnelReportService.php:166-179 | calculateStageProgress unbounded get()+groupBy per render; from/until optional.
- [S5] CONFIRMED high perf | packages/signals/src/Services/RetentionReportService.php:100-132 | cohorts() get() ALL identities + PHP group/filter; bounds optional.
- [S6] CONFIRMED medium bug | packages/signals/src/Actions/ResolveSession.php:106-115 | Race retry only on Pg 23505; MySQL 23000/1062 + SQLite rethrown.
- [S7] CONFIRMED medium security | packages/signals/src/Actions/ResolveSession.php:183-233 | CF-Connecting-IP/CF-IPCountry trusted from any client, no proxy check; stored IP/geo spoofable.
- [S8] CONFIRMED low bug | packages/signals/src/Services/SignalsIngestionRequestValidator.php:105-113 | mb_strlen (chars) vs bytes for max_bytes; multibyte payloads bypass cap.
- [S9] CONFIRMED low bug | packages/signals/src/Actions/IdentifySignalIdentity.php:126-131 | resolveSeenAt parse w/o try/catch; invalid seen_at via handle() 500s (controller has date rule).
- [S10] DOWNGRADED (was low) info security | packages/signals/src/Services/SignalsDashboardService.php:117-124 | withOwner(null) yields global-only (OwnerQuery:46-48), not cross-tenant; silent fail-open vs assert remains.
- [S11] CONFIRMED low perf | packages/signals/src/Actions/IngestSignalEvent.php:87-98 | Per-event session exists() + full save() on hot path (2 writes + 1 read).
- [S12] CONFIRMED low perf | packages/signals/src/Models/TrackedProperty.php:187-211 | deleting: Schema::hasTable each time + unbounded get()->each(delete) on growth experiments.
- [S13] CONFIRMED low security | packages/signals/config/signals.php:117 | property_allowlist includes cookie_value; raw cookie material persistable in event props.
- [AUD:B1] ADOPTED med-high security | packages/signals/src/Actions/IngestSignalEvent.php:260-262 | '*' allowlist returns props unfiltered, skips PII blocklist.
- [AUD:B2] ADOPTED medium bug | packages/signals/src/Actions/IngestSignalEvent.php:47-57 | idempotencyKey trusted-only; browser retries duplicate rows.
- [AUD:B3] ADOPTED low bug | packages/signals/src/Actions/IngestSignalEvent.php:76 | (int) revenue_minor truncates floats, accepts negatives; no validation.
- [AUD:B4] ADOPTED low security | packages/signals/src/Models/TrackedProperty.php:184 | write_key Str::random(40) plaintext; data-write-key + body/query; log leak.
- [AUD:B5] ADOPTED info note | packages/signals/src/Services/SignalsIngestionRequestValidator.php:51-73 | GOOD: strict timestamp/replay/hash_equals/RateLimiter dedup; per-prop/IP limits.
- [AUD:B6] ADOPTED info note | packages/signals/src/Services/SignalAlertEvaluator.php:128-136 | Perf GOOD mostly; exception: S3/S4/S5 unbounded get() verified in current source.

### Prior-audit chunk: filament-shipping

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-shipping` — Shipment/Return/Zone strip-then-`forOwner` + `whereRaw('0=1')` secure; `ShippingRateResource:55-56` scopes via `whereHas(zone forOwner)`. Widgets `withoutGlobalScope` without visible re-scope (confirm `OwnerContext::resolve()`).
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-shipping

- [H1] CONFIRMED high bug | packages/filament-shipping/src/Resources/ShippingRateResource/Schemas/ShippingRateForm.php:74 | (int)($state*100)/$state*100 w/o round (6+6+2 sites); 19.99->1998; weight path uses round.
- [H2] CONFIRMED medium perf | packages/filament-shipping/src/Support/ShippingStatsAggregator.php:81-91 | getAllStats: 4 counts for total + 4 again + returns = 9 queries; sum in PHP instead.
- [H3] CONFIRMED medium bug | packages/filament-shipping/src/Pages/ManifestPage.php:223-251 | mark_all_picked_up unbounded get()+filter then per-row update; no chunking (Shipped+date bounded).
- [H4] FALSE low security | packages/filament-shipping/src/Pages/FulfillmentQueue.php:132-143 | forOwner(null) is global-only (OwnerQuery:46-48), no leak; only badge(null) vs table(global) UX gap.
- [H5] CONFIRMED low security | packages/filament-shipping/src/Actions/PrintLabelAction.php:207-210 | Bulk errors interpolate $e->getMessage into notifications; single-label path uses generic msg.
- [AUD:B1] ADOPTED info note | packages/filament-shipping/src/Resources/ShippingRateResource.php:55-56 | Secure: strip+forOwner+whereRaw('0=1'); rates via whereHas(zone); widgets re-scope (Carrier:43-55).
- [AUD:B2] ADOPTED info note | packages/filament-shipping/src/Pages/ManifestPage.php:52-60 | G1 PASS: navigation group via config; sort keys compliant.
- [AUD:B3] ADOPTED medium perf | packages/filament-shipping/src/Pages/FulfillmentQueue.php:75-130 | G2: in-pkg badges cached 15s; global uncached-COUNT MEDIUM stands elsewhere.
- [AUD:B4] ADOPTED medium perf | packages/filament-shipping/src/Pages/FulfillmentQueue.php:142 | G3: customer eager-loaded; rates with('zone'); global N+1 MEDIUM stands elsewhere.
- [AUD:B5] ADOPTED medium perf | packages/filament-shipping/src/Resources/ShippingRateResource/Schemas/ShippingRateForm.php:31 | G4: zone options lazy scoped closure, no preload; global pluck pattern stands elsewhere.
- [AUD:B6] ADOPTED medium bug | packages/filament-shipping/src | G5: no in-pkg domain-leak citation; global LOW/MEDIUM note stands.
- [AUD:B7] ADOPTED medium perf | packages/filament-shipping/src/Widgets/CarrierPerformanceWidget.php:75-83 | G6: SUM(CASE) SQL agg in pkg; global residual-COUNT note stands.

### Prior-audit chunk: tax

### Prior-audit section
### tax
Bugs:
- `owner_*` fillable MEDIUM (was CRITICAL) — fact true, but `TaxRate/Zone saving` enforce owner/global-block; defense-in-depth.
- `TaxExemption status` fillable HIGH — bypasses `approve()/transitionStatus()`.
- `TaxZone deleting` bulk skips events LOW — harmless (Rate has no `deleting` hook).
Security: `TaxCalculator:127-160` LOW (was MEDIUM) — `exemptable_type/customer_type` from context, no morph allowlist; lookup owner-scoped so arbitrary string only misses, not leaks.
Performance: DONE (2026-09-13, §8 item 11) — `scopeForAddress:149-175` + `matchesAddress:177-205` MEDIUM — `orWhereJsonContains/Length` + PHP postcode loop per checkout. Fixed by design (no migration): request-scoped owner-aware resolver cache with zone/rate-write invalidation. The `(owner)` index sub-claim was FALSE — `nullableMorphs('owner')` already indexes (see §7).

### Migration-batch rows (§8, code may already be fixed)
| 7 | tax `(owner)` index on rates (§2) | FALSE — already exists via `nullableMorphs`; dropped, see §7 | — |

#### Verdicts: tax

- [T1] CONFIRMED high security | packages/tax/src/Actions/Exemption/RequestTaxExemption.php:15-23 | new TaxExemption($attributes) lets caller set Approved+verified_at+owner; DUP AUD:B2.
- [T2] CONFIRMED medium bug | packages/tax/src/Services/ZoneResolver/CompositeZoneResolver.php:48-55 | clearCache skips defaultResolver, which caches (Default:18,80-83); fallback zone stale.
- [T3] CONFIRMED medium bug | packages/tax/src/Models/TaxZone.php:151-170 | scopeForAddress ignores $postcode; resolver get()+PHP match (Address:103-111); req-cache only cuts repeats.
- [T4] CONFIRMED low bug | packages/tax/src/Services/TaxCalculator.php:224-227 | Unvalidated context currency into results + Money::{$currency} dispatch (TaxResultData:69-74).
- [T5] CONFIRMED low bug | packages/tax/src/Console/Commands/RecalculateTaxRatesCommand.php:30-49 | Counts rows then reports complete; no work. Same in SyncTaxZonesCommand:29-42.
- [T6] CONFIRMED low bug | packages/tax/src/Models/TaxZone.php:353-384 | Range mode strips non-digits numerically; alphanumeric postcodes (UK/CA) collapse + false-match.
- [AUD:B1] ADOPTED medium bug | packages/tax/src/Models/TaxZone.php:58-71 | owner_* fillable; saving guards enforce owner/global-block; defense-in-depth only.
- [AUD:B2] ADOPTED high bug | packages/tax/src/Models/TaxExemption.php:73-88 | status fillable bypasses approve()/transitionStatus(); DUP T1, counted once.
- [AUD:B3] ADOPTED low bug | packages/tax/src/Models/TaxZone.php:279-294 | Zone deleting bulk-deletes rates via query (skips events); harmless, no Rate deleting hook.
- [AUD:B4] ADOPTED low security | packages/tax/src/Services/TaxCalculator.php:127-160 | exemptable/customer_type w/o morph allowlist; owner-scoped so misses, not leaks.
- [AUD:B5] ADOPTED medium perf | packages/tax/src/Services/ZoneResolver/AddressZoneResolver.php:18 | Sec8 item11 DONE: owner-aware req cache + save/delete invalidation; T3 first-load remains.
- [AUD:Q7] FALSE medium perf | packages/tax/database/migrations/2001_03_01_000003_create_tax_rates_table.php | FALSE-list tax entry: nullableMorphs already creates (owner) index; no change made.

---

## Round 2 — `filament-jnt`, `persons`, `jnt`, `filament-orders`

### E2E findings (verbatim)

End-to-end review: filament-jnt, persons, jnt, filament-orders (workspace /Users/Saiffil/Herd/commerce). Read-only; inspected CONTEXT.md, config, routes, models, actions, services, webhooks, controllers, console, Filament resources/tables/infolists/pages/widgets, migrations, blade, data objects.

FINDINGS (severity | category | file:line | title — description | evidence | recommendation | confidence)

F1. HIGH | security/bug | packages/jnt/config/jnt.php:49-53 — Owner scoping OFF by default contradicts "owner-scoped all 5 models". Models gate on `jnt.owner.enabled=false`, and `JntTrackingService::getOrdersNeedingTrackingUpdate()` returns global unscoped list when disabled. In multi-tenant deploy this leaks cross-owner orders. Evidence: `'enabled' => env('JNT_OWNER_ENABLED', false)`. Recommendation: default `true` (or fail-closed in multitenant docs + assert in service provider); keep explicit-global escape hatch. Confidence: high.

F2. HIGH | performance/bug | packages/filament-orders/src/Pages/OrderTimelinePage.php:36-41 — `paginated(false)` loads ALL orders into one table (OOM on large tenants). Evidence: `OrderResource::table($table)->defaultSort(...)->paginated(false)`. Recommendation: remove `paginated(false)`; use default pagination + limit. Confidence: high.

F3. HIGH | security/performance | packages/jnt/src/Webhooks/ProcessJntWebhook.php:347-404 + packages/jnt/src/Data/WebhookData.php:60-84 — Unbounded webhook `details[]`: `bizContent` validated only as `required|string` (no max), then per-detail `JntTrackingEvent::create()` in a loop. Attacker-controlled array can exhaust DB/time. Evidence: `'bizContent' => ['required','string']` then `foreach ($details as $detail) { ...->create([...]) }`. Recommendation: enforce `max:` on bizContent + `count(details) <= N` (e.g. 500), reject oversize with 422, and batch insert/upsert (`insertOrIgnore`/`upsert` on event_hash). Confidence: high.

F4. HIGH | bug | packages/jnt/src/Webhooks/ProcessJntWebhook.php:493-500 (also JntTrackingService.php:85,116,208; TrackingData.php:83) — `CarbonImmutable::parse($value)` on attacker/carrier-controlled `scanTime` throws on malformed input, failing the whole webhook/sync. Evidence: `return CarbonImmutable::parse($value);` with only is_string check. Recommendation: wrap in try/catch or use `CarbonImmutable::tryParse`, treat unparseable as null + log. Confidence: high.

F5. MEDIUM | bug | packages/jnt/src/Services/JntStatusMapper.php:141-145 — `fromString()` never returns `Returned`: the `RETURN` branch precedes `RETURNED`, and 'RETURNED' contains 'RETURN'. Evidence: `str_contains(...,'RETURN'), ... => ReturnInitiated,` before `str_contains(...,'RETURNED') => Returned`. Recommendation: reorder (check RETURNED/RETURN COMPLETED before RETURN), add unit test. Confidence: high.

F6. MEDIUM | bug | packages/jnt/src/Data/OrderData.php:39, TrackingData.php:54, TrackingDetailData.php:65-69 — `fromApiArray()` uses required keys without isset (`$data['txlogisticId']`, `$data['billCode']`, `$data['scanTime']` etc.); malformed J&T responses cause undefined-key ErrorException → 500. Evidence: `orderId: $data['txlogisticId'],`. Recommendation: validate with `?? throw JntValidationException`, same for TrackingData/Detail. Confidence: high.

F7. MEDIUM | bug/validation | packages/jnt/src/Services/JntExpressService.php:89-94 — `createOrderFromArray()` bypasses `OrderBuilder` validation and posts raw arrays to the carrier. Evidence: `post('/api/order/addOrder', $orderData)` with no `Validator`. Recommendation: route array input through `OrderBuilder` validation or a shared validator; document as unsafe-advanced otherwise. Confidence: high.

F8. MEDIUM | performance/bug | packages/jnt/src/Services/JntExpressService.php:384-416,515-529 — `batchTrackParcels()`/`batchPrintWaybills()` fan out `Concurrency::run($tasks)` with no cap/chunk; huge input = process/connection exhaustion. Duplicate input IDs also collapse (`$tasks["order:{$orderId}"]`), silently dropping work. Recommendation: chunk (e.g. 10-25), cap total, preserve duplicates via list tasks with index keys. Confidence: high.

F9. MEDIUM | performance | packages/jnt/src/Services/JntTrackingService.php:115-169 — Per-detail `firstOrCreate()` inside `syncOrderTracking()` + `batchSyncTracking()` sequential API calls: N queries + N API calls, no queue/chunk. Evidence: `foreach (... as $detail) { ... JntTrackingEvent::firstOrCreate(...) }`. Recommendation: `upsert()` new events, queue batch syncs, reuse webhook event_hash dedupe. Confidence: high.

F10. MEDIUM | bug/security | packages/jnt/src/Models/JntOrder.php:89-136 (also Item/Parcel/TrackingEvent/WebhookLog `owner_*` in fillable) — `owner_type/owner_id` mass-assignable; children validate cross-owner on create but `JntOrder` itself has no creating guard in-package (relies on commerce-support HasOwner behavior, not inspected here). Evidence: `'owner_type','owner_id'` in `$fillable`. Recommendation: remove owner keys from fillable / force server-side assign via OwnerContext+OwnerWriteGuard. Confidence: med.

F11. MEDIUM | bug | packages/persons/src/Actions/CreatePersonAction.php:15-17 + Models/Person.php:125-174 — No validation; missing `name` becomes `''` and still saves (slug `person-<uuid>`), `status/published_at/searchable_name` caller-settable (partially renormalized). Evidence: `Person::query()->create($attributes)`. Recommendation: validate `name required|string|max:255` etc. in the Action; keep lifecycle normalization as backstop. Confidence: high.

F12. MEDIUM | bug/performance | packages/persons/src/Models/Person.php:202-227 + Data/PersonData.php:38 — `getFormattedNameAttribute()` queries `titleAssignments()->with('title.category')` on every access when graph incomplete; `PersonData::fromPerson()` calls it per person → N+1 in lists. Evidence: `->where('status',...)->with('title.category')->get()` inside accessor. Recommendation: eager-load in callers / add `scopeWithFormattedName()`; document. Confidence: high.

F13. MEDIUM | bug | packages/persons/src/Actions/AssignTitleAction.php:26-44, AssignCredentialAction.php:26-44 + migrations 000006/000008 — Check-then-insert duplicate guard with NO unique constraint on `(titleable_type,titleable_id,title_id)` / `(credentialable_type,credentialable_id,credential_id)` → concurrent double-assign creates duplicates; `$attributes` spread also mass-assigns dates/status unvalidated. Recommendation: add DB unique indexes + catch violation; validate attributes. Confidence: high.

F14. MEDIUM | bug | packages/jnt/src/Services/JntExpressService.php:28,557-568 + JntServiceProvider.php:168-176 — `JntExpressService` singleton snapshots `customerCode/password/baseUrl`; under Octane or per-owner credential switching it serves stale/wrong-tenant credentials. Evidence: `singleton(JntExpressService::class, ... new JntExpressService(customerCode: $config...))` + lazy `$this->client`. Recommendation: bind scoped (non-singleton) or resolve credentials per call/owner; never cache client across owners. Confidence: med.

F15. MEDIUM | performance | packages/filament-jnt/src/Resources/JntOrderResource/Schemas/JntOrderInfolist.php:254-275,281-301 — `RepeatableEntry(trackingEvents/items)` renders ALL rows + separate `->exists()` checks per section. Large orders = heavy page. Recommendation: cap with `->limit()`/paginated relation or `->visible(fn()=> $record->relationLoaded(...))` + eager load with limit. Confidence: high.

F16. MEDIUM | bug | packages/filament-orders/src/Widgets/OrderTimelineWidget.php:140-182 — Note `content` has no `maxLength`; `visibility` taken as `$data['visibility'] ?? 'internal'` with no `in:internal,customer` server check (Livewire-tamperable). Recommendation: add `->maxLength(2000)` + `->in([...])`/enum validation in `addNote()`. Confidence: med.

F17. LOW | security | packages/jnt/src/Console/Commands/Orders/OrderPrintCommand.php:36-39 + Data/PrintWaybillData.php:89-107 — Path traversal via CLI: `--path` and `{order-id}` interpolated into `base_path(sprintf('%s/%s', $path, $orderId.pdf))` then `mkdir+file_put_contents`. Requires CLI access but writes outside intended dir via `../`. Recommendation: sanitize with `basename()`, allowlist dir, reject `..`/separators. Confidence: high.

F18. LOW | security | packages/jnt/src/Http/Controllers/AwbController.php:45-49 — `Content-Disposition: filename="jnt_awb_{$orderId}..."` uses raw route param (quote/CRLF injection). Recommendation: slug/basename orderId for filename; use `filename*` encoding or fixed name. Confidence: med.

F19. LOW | security | packages/jnt/src/Console/Commands/Webhooks/WebhookTestCommand.php:21-45 — `--url` posts signed test payload to arbitrary URL (SSRF primitive if ever exposed beyond trusted CLI). `ConfigCheckCommand:175` GETs configured base URL (config-trusted). Recommendation: keep CLI-only, validate http(s) + deny metadata/link-local, document. Confidence: med.

F20. LOW | security | packages/filament-jnt/src/Actions/PrintAwbTableAction.php:67-71 — Opens carrier-supplied `urlContent` in new tab after only `FILTER_VALIDATE_URL` (allows evil host if J&T/MITM compromised; `json_encode` blocks JS injection). Recommendation: allowlist J&T hosts or proxy download like the base64 path. Confidence: med.

F21. LOW | performance | packages/filament-jnt/src/Support/NavigationBadgeHelper.php:30 + Widgets/JntStatsWidget.php:21-25 + jnt JntExpressService.php:217-272 — `Cache::remember`/`OwnerCache::get+put` without locks: stampede on 15-30s expiry (badges/widgets/tracking poll). Recommendation: `Cache::flexible`/locked remember, jitter TTL. Confidence: med.

F22. LOW | performance | packages/filament-jnt/.../JntOrderTable.php:62-64, JntTrackingEventTable.php:43-45, JntOrderInfolist.php:45-47 — `getNormalizedStatus()` invoked 3× per row (icon/color/label) via container resolve. Recommendation: compute once per record (memoize/static cache per request). Confidence: high.

F23. LOW | bug | packages/jnt/src/Listeners/SendShipmentNotifications.php:81-108 — `metadata['notification_email']` unvalidated: non-string (array) causes TypeError in anonymous class `readonly string $email`; arbitrary stored email = mail-to-attacker. Also `Notification::send()` on owner without Notifiable check can fail queue job. Recommendation: validate email string + `filter_var`, check `method_exists/uses Notifiable`, wrap send in try/catch. Confidence: high.

F24. LOW | bug | packages/jnt/src/Services/WebhookService.php:187-190 — `parseBizContent()` assumes list of arrays; associative/single-object payload causes TypeError in `TrackingData::fromApiArray`. Recommendation: normalize single object to list + `is_array` guard per item. Confidence: med.

F25. LOW | performance/bug | packages/persons/src/Models/Person.php:82-87 (same in Title/TitleCategory/CredentialDefinition/Affiliation) — `->get()->each->delete()` cascade loads all children (memory/N+1). Recommendation: `chunkById()` deletes or bulk delete where events not needed. Confidence: high.

F26. LOW | bug | packages/jnt/src/Shipping/JntShippingDriver.php:387-400 — Postcode range compare is lexicographic string compare; non-numeric postcodes misclassify region multiplier/ETA. Recommendation: normalize/validate `^\d{5}$` before range check, default 1x otherwise. Confidence: med.

POSITIVES (brief): owner-scoping correctly applied in Filament layers (BaseJntResource/OwnerUiScope, Jnt policies, OwnerWriteGuard revalidation in all 3 filament-jnt actions, filament-orders forOwner + invoice route Gate+throttle); webhook signature uses timing-safe compare + sanitized logging; AWB uses signed URL + OwnerSignedDownload binding; money consistently int minor units with TypeTransformer; single-aggregate stats queries (JntStatsAggregator, FilamentOrdersCache); indexes comprehensive (orders/tracking/parcels/webhook_calls, persons partial uniques); no SoftDeletes/FK violations seen; Blade `{{ }}` escaped and OrderInfolist uses `e()`+HtmlString (no XSS); no `unserialize/eval/shell` found; ReorderTitleAction PDO::quote + parameterized whereIn (no SQLi); Filament navigation via config group + getNavigationGroup correct.

### Prior-audit chunk: filament-jnt

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-jnt` GOOD pattern — `BaseJntResource` + owner-keyed cached badge + `OwnerUiScope`.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-jnt

- [R2:F15] CONFIRMED MEDIUM perf | packages/filament-jnt/src/Resources/JntOrderResource/Schemas/JntOrderInfolist.php:254 | RepeatableEntry renders ALL trackingEvents/items + exists() per section; cap it (:254-275, :281-301)
- [R2:F20] CONFIRMED LOW sec | packages/filament-jnt/src/Actions/PrintAwbTableAction.php:70 | Carrier urlContent opened in new tab after FILTER_VALIDATE_URL only; allowlist hosts or proxy (:67-71)
- [R2:F21] CONFIRMED LOW perf | packages/filament-jnt/src/Support/NavigationBadgeHelper.php:30 | Cache::remember w/o lock stampedes at 30s expiry; same JntStatsWidget:21, jnt OwnerCache get+put
- [R2:F22] CONFIRMED LOW perf | packages/filament-jnt/src/Resources/JntOrderResource/Tables/JntOrderTable.php:62 | getNormalizedStatus() 3x/row via app(); same TrackingEventTable:43-45, Infolist:45-47+263-265
- [AUD:B1] ADOPTED OK info | packages/filament-jnt/src/Resources/BaseJntResource.php:36 | GOOD pattern holds: cached owner-keyed badge + OwnerUiScope scoping (:36-62)
- [AUD:B2] ADOPTED OK info | packages/filament-jnt/src/Resources/BaseJntResource.php:21 | G1 PASS: no static $navigationGroup; config group + sort key (:21-29)
- [AUD:B3] ADOPTED MEDIUM perf | packages/filament-jnt/src/Support/NavigationBadgeHelper.php:30 | N/A here: cited as cached-badge exemplar; Cache::remember 30s owner-keyed confirmed
- [AUD:B4] ADOPTED MEDIUM perf | packages/filament-jnt/src/Resources/JntOrderResource/Tables/JntOrderTable.php:28 | N/A here: direct-attribute columns only, no relation.field w/o with(); worst are affiliates/events
- [AUD:B5] ADOPTED MEDIUM perf | packages/filament-jnt/src/Resources/JntOrderResource/Tables/JntOrderTable.php:101 | N/A here: no pluck()+preload() Select seen; audit clusters this in affiliates/addressing/persons
- [AUD:B6] ADOPTED MEDIUM bug | packages/filament-jnt/src/Support/NavigationBadgeHelper.php:14 | N/A here: no domain leakage seen; audit cites vouchers widgets only
- [AUD:B7] ADOPTED MEDIUM perf | packages/filament-jnt/src/Widgets/JntStatsWidget.php:91 | N/A here: single-aggregate JntStatsAggregator (:91-94); residual is cashier-chip/inventory

### Prior-audit chunk: persons

### Prior-audit section
### persons (global identity — no owner scope correct)
Bugs:
- `Models/Person.php:82-87` MEDIUM (was HIGH) — `names/titleAssignments/credentials/affiliations ->get()->each->delete()`, no txn/chunk; partial-delete orphans.
- `Models/Person.php:126-174` MEDIUM — up to 100× `exists()` slug probes + extra `names()` query per save; no 23000 retry → 500 on race.
- `Models/Person.php:255-261` LOW — `transitionStatus(): void` mutate-no-save (style only).
- `Models/Person.php:59-71` LOW (was MEDIUM) — `slug/searchable_name/status/published_at` fillable, but `saving` overwrites slug/searchable + `syncStatusLifecycle` repairs `published_at`.
Security: clean — `TitleAssignment saving` existence checks; `ReorderTitleAction` PDO-quoted `CASE` + lock + txn.
Performance:
- `getFormattedNameAttribute:202-227` MEDIUM N+1 — lazy `titleAssignments()->where(Active)->with(title.category)->get()`; eager-load or `scopeWithFormattedName`.
- `TitleAssignment saving` 1–2 `exists()` per row LOW — bulk import should `whereIn` prefetch.
- `Person:82-87` cascade is also perf: N+1 deletes, no chunk.

#### Verdicts: persons

- [R2:F11] CONFIRMED MEDIUM bug | packages/persons/src/Actions/CreatePersonAction.php:16 | No validation; missing name saves as '' (col NOT NULL, '' allowed); lifecycle backstop only
- [R2:F12] CONFIRMED MEDIUM perf | packages/persons/src/Models/Person.php:223 | Accessor queries titleAssignments+graph when incomplete; PersonData::fromPerson per row; DUP AUD:B6
- [R2:F13] CONFIRMED MEDIUM bug | packages/persons/src/Actions/AssignTitleAction.php:26 | Check-then-insert, no unique idx (000006/000008 plain indexes); $attributes spread unvalidated; same Credential
- [R2:F25] CONFIRMED MEDIUM bug | packages/persons/src/Models/Person.php:82 | Adopt audit MEDIUM (was LOW): cascade get()->each->delete(), no txn/chunk; DUP AUD:B1
- [AUD:B1] ADOPTED MEDIUM bug | packages/persons/src/Models/Person.php:82 | Cascade no txn/chunk, partial-delete orphans; DUP R2:F25, counted once
- [AUD:B2] ADOPTED MEDIUM bug | packages/persons/src/Models/Person.php:142 | Up to 100 exists() slug probes per save, no 23000 retry; LogicException after budget (:142-164)
- [AUD:B3] ADOPTED LOW bug | packages/persons/src/Models/Person.php:255 | transitionStatus() mutates w/o save, style-only (:255-261)
- [AUD:B4] ADOPTED LOW bug | packages/persons/src/Models/Person.php:59 | slug/searchable/status/published_at fillable but saving hook overwrites/repairs (:75-80)
- [AUD:B5] ADOPTED OK info | packages/persons/src/Models/TitleAssignment.php:60 | Security clean: saving existence checks confirmed (:60-70); ReorderTitleAction part per audit
- [AUD:B6] ADOPTED MEDIUM perf | packages/persons/src/Models/Person.php:202 | N+1 formatted-name accessor; DUP R2:F12, counted once
- [AUD:B7] ADOPTED LOW perf | packages/persons/src/Models/TitleAssignment.php:61 | 1-2 exists() per row on save; bulk import should prefetch (:61-68)
- [AUD:B8] ADOPTED MEDIUM perf | packages/persons/src/Models/Person.php:82 | Cascade also perf (N+1 deletes); DUP AUD:B1/R2:F25, counted once

### Prior-audit chunk: jnt

### Prior-audit section
### jnt
Bugs:
- `ProcessJntWebhook:493-500` HIGH — `CarbonImmutable::parse()` throws on bad `scanTime` in `usort` comparator + sync paths → retry loop.
- `JntTrackingEvent:80-106`, `Parcel:49-70`, `Item:50-74` HIGH — inherit-without-authorize (unscoped parent copy, no `OwnerContext::resolve()` check; contrast `cart/Condition:536-553`).
- Per-detail inserts MEDIUM — per-row `create()` + catch-unique-skip; use `upsert(event_hash)` (unique exists).
- Last-write-wins MEDIUM — `fill($updates)->save():461-462` no txn/lock.
- `JntWebhookLog:125-128` MEDIUM — hardcoded `'webhook_calls'`, ignores prefix/tables.
- `JntOrder:224-231` LOW — non-atomic cascade, no txn/chunk.
Security:
- `JntSpatieSignatureValidator:17-22` CRITICAL (narrowed) — `verify_signature=false` bypass with NO prod fail-closed (contrast checkout). Default `true` + empty-secret fails closed, so requires explicit opt-out.
- `JntTrackingEvent/Parcel/Item creating` HIGH (above).
- `WebhookController:75-103` MEDIUM — distinct 401 vs 422 oracle; use uniform `200+{code:0}` per J&T spec.
- `verifyAndParse:155-165` LOW — signs parsed `input('bizContent')` not raw `getContent()`; false negatives.
- `AwbController:17-53` GOOD reference — `hasValidSignature` + `OwnerSignedDownload` + `no-store`.
Performance:
- O(n log n) re-sort per webhook MEDIUM — J&T already chronological; single max-scan O(n).
- `latest('scan_time')` per order N+1 LOW — `latestOfMany`/subquery.

---

### Prior-audit fix-first rows
| 5 | jnt | `Webhooks/JntSpatieSignatureValidator.php:17-22` | `verify_signature=false` accepted with no prod fail-closed (checkout refuses) | CRITICAL |
| — | jnt | `ProcessJntWebhook:493-500` | `CarbonImmutable::parse()` throws on bad `scanTime` → retry loop | HIGH |
| — | jnt | `JntTrackingEvent/Parcel/Item creating` | Inherit-without-authorize, no context check | HIGH |

#### Verdicts: jnt

- [R2:F1] DOWNGRADED (was HIGH) MEDIUM sec | packages/jnt/config/jnt.php:50 | Docs show default true (09-multitenancy.md:41) but config false; global list only when scoping off by design
- [R2:F3] CONFIRMED HIGH sec | packages/jnt/src/Data/WebhookData.php:61 | bizContent required|string, no max; uncapped per-detail create loop (ProcessJntWebhook:348-404)
- [R2:F4] CONFIRMED HIGH bug | packages/jnt/src/Webhooks/ProcessJntWebhook.php:499 | parse() throws on bad scanTime; same JntTrackingService:85,116,208 + TrackingData:83; DUP AUD:B1
- [R2:F5] CONFIRMED MEDIUM bug | packages/jnt/src/Services/JntStatusMapper.php:141 | RETURN branch shadows RETURNED ('RETURNED' contains 'RETURN'); reorder + test (:141-145)
- [R2:F6] CONFIRMED MEDIUM bug | packages/jnt/src/Data/OrderData.php:39 | Required keys w/o isset ($data['txlogisticId']); same TrackingData:54, TrackingDetailData:65-69
- [R2:F7] DOWNGRADED (was MEDIUM) LOW bug | packages/jnt/src/Services/JntExpressService.php:89 | Docblock already warns 'less type safety' passthrough; unvalidated by design, callers opt in (:89-94)
- [R2:F8] CONFIRMED MEDIUM perf | packages/jnt/src/Services/JntExpressService.php:395 | Uncapped Concurrency::run; keyed tasks drop dup IDs (:395,403,524); chunk + index keys
- [R2:F9] CONFIRMED MEDIUM perf | packages/jnt/src/Services/JntTrackingService.php:115 | firstOrCreate per detail + sequential batchSync; upsert + queue; DUP AUD:B3 partial
- [R2:F10] CONFIRMED MEDIUM bug | packages/jnt/src/Models/JntOrder.php:89 | owner_type/id fillable, no creating guard on JntOrder itself (children validate); relies on HasOwner
- [R2:F14] CONFIRMED MEDIUM bug | packages/jnt/src/JntServiceProvider.php:168 | Singleton snapshots customerCode/password/baseUrl; stale under Octane/owner switching (:168-176)
- [R2:F17] CONFIRMED LOW sec | packages/jnt/src/Console/Commands/Orders/OrderPrintCommand.php:37 | CLI --path/order-id traversal into base_path()+mkdir+write; sanitize w/ basename (:36-39)
- [R2:F18] CONFIRMED LOW sec | packages/jnt/src/Http/Controllers/AwbController.php:45 | Raw orderId in Content-Disposition filename; signed-payload binding limits abuse (:45-49)
- [R2:F19] CONFIRMED LOW sec | packages/jnt/src/Console/Commands/Webhooks/WebhookTestCommand.php:21 | CLI --url posts signed payload anywhere (SSRF primitive); keep CLI-only (:21-45)
- [R2:F23] CONFIRMED LOW bug | packages/jnt/src/Listeners/SendShipmentNotifications.php:92 | notification_email unvalidated (array => TypeError); owner w/o Notifiable can fail job (:92-104)
- [R2:F24] CONFIRMED LOW bug | packages/jnt/src/Services/WebhookService.php:187 | Assoc/single-object bizContent => TypeError in fromApiArray; normalize to list (:187-190)
- [R2:F26] CONFIRMED LOW bug | packages/jnt/src/Shipping/JntShippingDriver.php:392 | Lexicographic postcode range compare; non-numeric postcodes misclassify (:387-400)
- [AUD:B1] ADOPTED HIGH bug | packages/jnt/src/Webhooks/ProcessJntWebhook.php:493 | parse() throws in usort comparator + sync paths; DUP R2:F4/AUD:Q#2, counted once
- [AUD:B2] ADOPTED HIGH sec | packages/jnt/src/Models/JntTrackingEvent.php:80 | Guards validate preset owner + inherit, but no OwnerContext::resolve() check; claim holds (:80-106)
- [AUD:B3] ADOPTED MEDIUM perf | packages/jnt/src/Webhooks/ProcessJntWebhook.php:356 | Per-row create()+catch-unique-skip; use upsert(event_hash); DUP R2:F9/F3 partial
- [AUD:B4] ADOPTED MEDIUM bug | packages/jnt/src/Webhooks/ProcessJntWebhook.php:461 | fill($updates)->save() no txn/lock; last-write-wins (:461-462)
- [AUD:B5] ADOPTED MEDIUM bug | packages/jnt/src/Models/JntWebhookLog.php:125 | Hardcoded 'webhook_calls', ignores prefix/tables (:125-128)
- [AUD:B6] ADOPTED LOW bug | packages/jnt/src/Models/JntOrder.php:224 | Non-atomic cascade, no txn/chunk (:225-231)
- [AUD:B7] ADOPTED CRITICAL sec | packages/jnt/src/Webhooks/JntSpatieSignatureValidator.php:17 | verify_signature=false bypass, no prod fail-closed; default true + empty-secret closed; DUP Q#1
- [AUD:B8] ADOPTED HIGH sec | packages/jnt/src/Models/JntOrderItem.php:50 | Same inherit-without-authorize as B2; DUP AUD:B2, counted once
- [AUD:B9] ADOPTED MEDIUM sec | packages/jnt/src/Http/Controllers/WebhookController.php:83 | Distinct 401 (:103) vs 422 (:83,93) oracle; use uniform 200+{code:0}
- [AUD:B10] ADOPTED LOW sec | packages/jnt/src/Services/WebhookService.php:155 | Signs parsed input('bizContent') not raw getContent(); false negatives (:155-165)
- [AUD:B11] ADOPTED OK info | packages/jnt/src/Http/Controllers/AwbController.php:17 | GOOD ref holds: hasValidSignature + OwnerSignedDownload + no-store (:17-53)
- [AUD:B12] ADOPTED MEDIUM perf | packages/jnt/src/Webhooks/ProcessJntWebhook.php:477 | O(n log n) re-sort per webhook; J&T already chronological, single max-scan O(n) (:477-486)
- [AUD:B13] ADOPTED LOW perf | packages/jnt/src/Models/JntOrder.php:215 | latest('scan_time') per order N+1; latestOfMany/subquery (:215-218)
- [AUD:Q#1] ADOPTED CRITICAL sec | packages/jnt/src/Webhooks/JntSpatieSignatureValidator.php:17 | Queue row; DUP AUD:B7, counted once
- [AUD:Q#2] ADOPTED HIGH bug | packages/jnt/src/Webhooks/ProcessJntWebhook.php:493 | Queue row; DUP AUD:B1 + R2:F4, counted once
- [AUD:Q#3] ADOPTED HIGH sec | packages/jnt/src/Models/JntOrderParcel.php:49 | Queue row; DUP AUD:B2, counted once

### Prior-audit chunk: filament-orders

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-orders` GOOD exemplary — `throttle+FilamentAuthenticate`, 404 when owner unresolved, `forOwner+findOrFail`, `Gate::allows('view')` download.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-orders

- [R2:F2] CONFIRMED HIGH perf | packages/filament-orders/src/Pages/OrderTimelinePage.php:40 | paginated(false) loads ALL orders into one table; restore pagination (:36-41)
- [R2:F16] CONFIRMED MEDIUM bug | packages/filament-orders/src/Widgets/OrderTimelineWidget.php:140 | Note content no maxLength; visibility w/o in: server check (:140-182)
- [AUD:B1] ADOPTED OK info | packages/filament-orders/src/Resources/OrderResource.php:53 | GOOD exemplary: forOwner (:53-60) + cached badge (:62-68); throttle/Gate part per audit
- [AUD:B2] ADOPTED OK info | packages/filament-orders/src/Resources/OrderResource.php:38 | G1 PASS: config group/sort, no static group (:38-46)
- [AUD:B3] ADOPTED MEDIUM perf | packages/filament-orders/src/Resources/OrderResource.php:62 | N/A here: cited as cached exemplar; FilamentOrdersCache::rememberStats (:62-68)
- [AUD:B4] ADOPTED MEDIUM perf | packages/filament-orders/src/Resources/OrderResource.php:53 | N/A here: ->with(['customer']) present; worst clusters are affiliates/events (:53-60)
- [AUD:B5] ADOPTED MEDIUM perf | packages/filament-orders/src/Widgets/OrderTimelineWidget.php:146 | N/A here: static-option Select only; clusters are affiliates/addressing/persons
- [AUD:B6] ADOPTED MEDIUM bug | packages/filament-orders/src/Resources/OrderResource.php:62 | N/A here: no domain leakage seen; audit cites vouchers widgets only
- [AUD:B7] ADOPTED MEDIUM perf | packages/filament-orders/src/Resources/OrderResource.php:62 | N/A here: cached stats badge; residual is cashier-chip/inventory

---

## Round 2 — `filament-organizations`, `products`, `filament-pricing`, `references`

### E2E findings (verbatim)

E2E review: filament-organizations, products, filament-pricing, references (workspace /Users/Saiffil/Herd/commerce). Read-only via search+read. No package-local tests/ dirs; central Pest suites at tests/src/Products, tests/src/References used as contract context.

POSITIVES (brief): uuid PKs everywhere (HasUuids); no DB FK constraints/cascades (foreignUuid unconstrained) and no SoftDeletes — matches repo rules; money as int minor units (unsignedBigInteger, ProductPricing::formatMinor); owner scoping via commerce-support with fail-closed OwnerScope (throws without context); variant generation cap + queue threshold; simulator/RM searches limit(50) and mostly owner-scoped; Reference parent owner guard (OwnerWriteGuard) + cycle-safe subtree collect; Organization list scoped to member orgs + authorizeRecord on mutations; no Octane-unsafe static state found; no raw SQL/user-input into raw queries (only static whereRaw('1 = 0')).

=== PRODUCTS (domain) ===
P1 — high / bug — src/Models/Product.php:817 + src/Models/Option.php:218-219 — Mass deletes skip model events, orphaning OptionValues and variant pivots. Evidence: `$product->options()->delete();` is a query-builder mass delete (no deleting events), so `Option::deleting` never runs; and `Option::deleting` itself does `$option->values()->delete()` (mass delete), so `OptionValue::deleting` (`$optionValue->variants()->detach()`) never runs. Result: orphaned option_values rows and orphaned product_variant_options pivot rows on every product/option delete (variants handled correctly via `->each(delete)` at Product.php:815). Recommend: `each(fn($o)=>$o->delete())` in both places. Confidence: high.

P2 — high / bug — src/Strategies/MatrixVariantGenerator.php:94-99 — SKU dedup ignores product_id, leaking cross-product variants. Evidence: `Variant::query()->forOwner($owner,false)->where('sku',$sku)->first()` — no `where('product_id',...)`. If two same-owner products share a parent SKU (nullable + no prod unique index, see P3), regeneration for product B returns/attaches product A's variant and skips creation. Recommend: add product_id constraint. Confidence: high.

P3 — high / bug+performance — database/migrations/2001_01_01_000001*:84-89, 000004*:58-62, 000012*:39-43 (same pattern in others) — Identity unique indexes only created in local/development/testing. Evidence: `if (! app()->environment(['local','development','testing'])) return; ProductIdentityIndexes::owner(...)`. Production has NO slug/sku uniqueness and NO slug/sku indexes: app-level `EnforcesOwnerUniqueIdentity` check is TOCTOU-racy (concurrent duplicates), and slug route binding + uniqueness probes full-scan. Recommend: always create partial unique indexes (all envs). Confidence: high.

P4 — high / bug (DoS) — src/Models/Category.php:303-361,435-476 — No parent validation (no cycle/self check, no owner check on parent_id) + unguarded recursion. Evidence: booted() validates only owner columns; `getAncestors()` (`while ($category->parent !== null)`) has no visited set; `getNestedTree()` recurses via lazy `children`. Any writer can create A↔B cycle (or self-parent) → infinite loop / stack overflow in getAncestors/getDepth/getFullPath/getFullSlug/getNestedTree. Recommend: validate parent (exists, same owner, not self/descendant) in saving hook. Confidence: high.

P5 — medium / bug — src/Actions/CreateProduct.php:18-20, src/Actions/UpdateProduct.php:22-25, src/Models/Product.php:156-160 — Double domain-event dispatch. Evidence: model `$dispatchesEvents = ['created'=>ProductCreated,'updated'=>ProductUpdated,...]` AND actions explicitly `ProductCreated::dispatch($product)` / `ProductUpdated::dispatch($fresh)` (different instances). Subscribers (indexing, notifications) run twice. Recommend: remove explicit dispatch, rely on model events. Confidence: high.

P6 — medium / security — src/Models/Collection.php:419-439 — Automatic-collection conditions allow arbitrary column/operator from fillable JSON. Evidence: `default => $query->where($field, $operator, $value)` with `$field/$operator/$value` from `conditions` (fillable, unvalidated). Column names are grammar-escaped (not SQLi) but any writer can probe arbitrary columns (cost, metadata) and invalid operators throw 500s. Mitigated to own tenant by owner scoping. Recommend: allowlist fields/operators. Confidence: high.

P7 — medium / performance — src/Models/Collection.php:245-278 — Unbounded `get()` + `sync()` in getMatchingProducts/rebuildProductList. Large automatic collections load all matching products into memory and sync huge pivot sets. Recommend: chunk/cursor + syncWithoutDetaching or batched sync. Confidence: high.

P8 — medium / bug — src/Actions/ApplyAttributeChanges.php:39-70 — Bypasses type serialization, silent no-ops, nullable fresh(). Evidence: `update(['value'=>$value])` raw (vs `setCustomAttribute` → `serializeValue`); array/date values corrupt to "Array"; unknown codes silently skipped; `$product->fresh()` may be null against non-nullable return → TypeError. Recommend: reuse setCustomAttribute path, error on unknown codes, handle null fresh. Confidence: high.

P9 — medium / security — src/Models/Product.php:405-439, src/Models/Variant.php:245-250, migration string(3) currency — currency fillable with no allowlist → dynamic `Money::$currency(...)` crash. Any writer sets currency='XX' → formatting/money accessors throw (500). Recommend: ISO-4217 allowlist validation + enum/cast. Confidence: high (crash type depends on akaunting/money version: med).

P10 — medium / security — src/Models/AttributeValue.php:186-200 — attributable_type accepts ANY Model class. Evidence: `class_exists + is_a(Model)` then queries it; only Product/Variant intended. Lets writers attach attribute rows to User/Order/etc (junk rows, cross-model mixing, id existence oracle). Recommend: allowlist [Product::class, Variant::class]. Confidence: high.

P11 — medium / performance — N+1 cluster — Variant.php:213-240,331-354 (product/optionValues per call: getDisplayImagesAttribute, getEffectivePrice, getOptionSummary, getFullName), Product.php:690-718 (getStockQuantity fans out per-variant inventory calls), Category.php:370-395 (getProductCount/getAllProducts recurse one query per category + in-memory merge/unique). Recommend: eager-load in callers,皆 bulk inventory API, iterative+cached trees. Confidence: high.

P12 — low / bug — src/Models/Variant.php:381-387 — Inventory outage swallowed → false out-of-stock. Evidence: `catch (Throwable) { return 0; }` while Product falls back to local stock. Recommend: propagate or fail-open consistently + log. Confidence: high.

P13 — low / bug — src/Concerns/EnforcesOwnerUniqueIdentity.php:39-90 — App-level uniqueness is racy and fabricates `UniqueConstraintViolationException` with synthetic SQL on create (may confuse retry/alert handlers). Moot once P3 fixed. Confidence: med.

P14 — low / performance — database/factories/ProductFactory.php:53 — `Schema::getColumnListing()` per factory definition (schema query per product in seeds/tests). Recommend: cache columns statically per process. Confidence: high.

P15 — low / security — src/Jobs/GenerateVariantsJob.php:29-41 — performJob uses `withoutOwnerScope()->whereKey(productId)` without verifying product vs job owner context (payload tamper → cross-tenant generation attempt; currently fails closed via Variant creating guards → job-failure/retry nuisance). Recommend: `OwnerWriteGuard::findOrFailForOwner`. Confidence: med.

P16 — low / security — src/Models/OptionValue.php:164-175 — getSwatchStyle interpolates unvalidated `swatch_color`/`swatch_image` into CSS (`background-color: {...}`, `url('{...}')`); stored CSS-breakout/XSS if rendered raw (`{!! !!}`). Recommend: validate color hex + URL-encode/escape at render. Confidence: med (no raw-render call site found in scope).

=== FILAMENT-PRICING (adapter) ===
F1 — high / security — src/Widgets/PricingStatsWidget.php:19-21 — PriceList count unscoped (cross-tenant leak). Evidence: `PriceList::query()->active()->count()` with no forOwner, while promotions query just below IS owner-scoped. Every tenant sees global active-list count. Recommend: mirror promotion scoping. Confidence: high.

F2 — high / security — src/Resources/PriceListResource/RelationManagers/PricesRelationManager.php:103-138 — Arbitrary model class from user input + no save-time revalidation. Evidence: `getOptionLabelUsing` accepts any `$type` with `class_exists + is_a(Model)` then `$type::query()` (TiersRelationManager:118 correctly allowlists Product/Variant — inconsistency confirms bug); Create/Edit actions never revalidate priceable_type/id server-side, so prices attach to arbitrary models. Recommend: allowlist + OwnerWriteGuard revalidation in mutateFormDataUsing/before hooks. Confidence: high.

F3 — high / security — src/Pages/ManagePricingSettings.php:154-177 — Global pricing settings mutable by any panel user, validation bypassed. Evidence: `save()` reads raw `$this->data` (never `$this->form->getState()`), so min/max rules (e.g. decimalPlaces 0-4) unenforced; no `canAccess`/policy; Spatie settings are global → one tenant's user changes currency/rounding/min-max for ALL tenants. Recommend: admin-only authorization + validated state + per-owner settings or explicit global-only guard. Confidence: high.

F4 — medium / security — src/Pages/PriceSimulator.php:346-438 — calculate() uses unvalidated raw state. Evidence: `$data = $this->data ?? []`; direct `$data['product_type']`, `(int)$data['quantity']` (0/negative/huge/"abc"→0), raw `effective_at` into calculator. Form rules (required/minValue) bypassed via direct Livewire call. Recommend: `$this->form->getState()` + quantity bounds. Confidence: high.

F5 — medium / security — src/Resources/PriceListResource/Schemas/PriceListForm.php:37-41 — slug `unique(ignoreRecord:true)` is global, not owner-scoped → cross-tenant slug blocking + existence oracle. Recommend: owner-scoped unique rule (or per-owner partial index + scoped rule). Confidence: high.

F6 — low / security — LIKE wildcard injection (unescaped %/_ in search) → over-broad matches/DoS: PriceSimulator.php:119,183,286; PricesRelationManager.php:69,92; TiersRelationManager.php:75,98. Recommend: escape LIKE specials. Confidence: high.

F7 — low / performance — TiersRelationManager.php:217-227 — table state closures `loadMissing('tierable')` (+product) per row → 2N queries/page. Recommend: eager-load in table query. Confidence: high.

F8 — low / bug — Null-unsafe `$v->product->name` on possibly-orphaned variants (500), inconsistent with PriceSimulator's `?->`: PricesRelationManager.php:97,134; TiersRelationManager.php:103,139. Recommend: null-safe + fallback. Confidence: high.

F9 — low / security — No policies in pricing package (search found zero Policy/Gate in pricing/src), so PriceList/Price/Tier CRUD relies on Filament defaults. Recommend: add owner-aware policies like products. Confidence: med.

=== REFERENCES (domain) ===
R1 — high / security — database/migrations/2000_01_01_000001*:21 — slug globally unique in multi-tenant model. Evidence: `$table->string('slug')->unique()`. Cross-tenant slug squatting/blocking + existence oracle; contradicts owner-scoping. Recommend: drop global unique, add per-owner (+global) partial uniques. Confidence: high.

R2 — medium / bug — same migration (whole file) — no `down()` method; rollback leaves table behind. Recommend: add dropIfExists. Confidence: high.

R3 — medium / bug — src/Models/Reference.php:95-122,235-260,80-93 — custom delete() gaps. (a) descendants mass-deleted without child model events (no per-child deleting/deleted observers); (b) media cleanup `->get()->each(delete)` unbounded (memory on huge subtrees); (c) `collectSubtreeIds()` uses scoped `static::query()` so children outside current scope (e.g. owned children of a global parent) are orphaned, not deleted; (d) saving guard checks parent ownership but not self-parent/cycles. Recommend: chunked deletes with events (or documented mass-delete), chunked media delete, scope-aware subtree collection, parent≠self/descendant validation. Confidence: high (a,b,d); med (c — depends on global+owned mixing).

R4 — medium / security — No Policy/Gate for Reference (service provider registers none) vs products' 10 policies — auth left to consumers. Recommend: ship owner-aware ReferencePolicy. Confidence: med.

R5 — low / security — src/Models/Reference.php:59-76 — Validation gaps: unbounded JSON (reference_parts/metadata → oversized-payload DoS), year/isbn/url/language unvalidated (negative year, non-URL, overlong). No Action layer. Recommend: FormRequest/Action validation. Confidence: high.

R6 — low / bug — src/Models/Reference.php:124-127 vs config — getTable() default 'ref_references' mismatches config default 'references' (stale fallback when config unloaded). Recommend: align defaults. Confidence: high.

R7 — low / bug — Reference owns `reference_parts` but doesn't use `HasReferenceParts` trait (helpers only tested on a stub model) — DX inconsistency; consumers hand-roll JSON. Recommend: use trait on Reference or document. Confidence: high.

Positive note: Reference fillable correctly EXCLUDES owner_type/owner_id (secure default, unlike products models which mass-assign owner + rely on guards) — keep, and consider aligning products.

=== FILAMENT-ORGANIZATIONS (adapter) ===
O1 — medium / bug — Pages/EditOrganization.php:16-24 + Resources/OrganizationResource.php:76-78 — Direct `fill($data)->save()` bypasses domain action; slug editable on edit with no unique rule → duplicate slugs, bypasses domain invariants (form limits mass-assignment to 3 fields, so contained). Recommend: domain UpdateOrganizationAction + unique rule. Confidence: high.

O2 — medium / security — RelationManagers/InvitationsRelationManager.php (whole) — No revoke/resend/delete row actions though `membership/.../RevokeInvitationAction` exists → stale invitations irrevocable from UI. Recommend: add revoke action (authorize organization.manage-members). Confidence: high.

O3 — low / security — MembersRelationManager.php:45-46 — addMember email existence oracle (422 'User not found' vs success) + exact-match lookup (collation-dependent case behavior). Recommend: generic message + normalized lookup. Confidence: med.

O4 — low / security — MembersRelationManager.php:60,67 — Owner-role protection is `visible()`-only in UI; server enforcement delegated to membership actions (Organization::assertMemberCanBeAdded seen blocking owner demotion — defense-in-depth OK if Remove/Change actions enforce). Recommend: assert/verify in action closures. Confidence: med.

O5 — low / performance — Pages/ViewOrganization.php:100-106 — transfer-ownership options `->get()` all members unbounded. Recommend: searchable async select. Confidence: high.

O6 — low / security — Resources/OrganizationResource.php:42-45 — any authenticated user can create orgs (canCreate=true); ensure intended + rate-limit to prevent org-creation spam. Confidence: med.

Unresolved/none material: XSS/SSRF/path-traversal/deserialization — no sinks found in scope (no unserialize/eval/shell, no URL fetching, no file-path building from input, blade uses escaped `{{ }}`); cache stampedes N/A (no caching); Octane state clean.

### Prior-audit chunk: filament-organizations

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-organizations

- [O1] DOWNGRADED (was medium) low bug | Resources/OrganizationResource/Pages/EditOrganization.php:16 | fill()->save() skips domain, slug has no unique rule; schema unique makes dupes 500, not silent; no UpdateAction exists
- [O2] CONFIRMED medium security | Resources/OrganizationResource/RelationManagers/InvitationsRelationManager.php:34 | invite header action only, no revoke/resend/delete row actions; RevokeInvitationAction exists in membership but unused
- [O3] CONFIRMED low security | Resources/OrganizationResource/RelationManagers/MembersRelationManager.php:45 | exact where('email') lookup + 422 'User not found' oracle; case behavior collation-dependent
- [O4] FALSE low security | Resources/OrganizationResource/RelationManagers/MembersRelationManager.php:60 | server enforced: Remove:36 assertMemberCanBeRemoved, Change:32 assertMemberRoleCanChange; Organization guard blocks owner demote/remove
- [O5] CONFIRMED low performance | Resources/OrganizationResource/Pages/ViewOrganization.php:100 | transfer-ownership members()->whereKeyNot()->get() unbounded; scoping correct per FALSE-list, perf only
- [O6] CONFIRMED low security | Resources/OrganizationResource.php:42 | canCreate true for any authenticated user; CreateOrganization page has no rate-limit; intent unconfirmed
- [AUD:B1] ADOPTED - note | Resources/OrganizationResource.php:27 | G1 PASS: getNavigationGroup/sort config-driven, no static $navigationGroup; compliant but fragile
- [AUD:B2] ADOPTED medium performance | src/ | G2 N/A here: no getNavigationBadge in pkg; global uncached-COUNT note applies to badged resources elsewhere
- [AUD:B3] ADOPTED medium performance | Resources/OrganizationResource/RelationManagers/MembersRelationManager.php:32 | G3 N/A here: only pivot.* columns, no relation.field TextColumn lacking with()
- [AUD:B4] ADOPTED medium performance | Resources/OrganizationResource/RelationManagers/MembersRelationManager.php:39 | G4 N/A here: role selects use enum options; no Model::pluck()->all() preload
- [AUD:B5] ADOPTED low bug | src/ | G5 N/A here: no domain-leakage helpers found in pkg
- [AUD:B6] ADOPTED medium performance | src/ | G6 N/A here: no get()->sum(fn) or per-render count() clusters found

### Prior-audit chunk: products

### Prior-audit section
### products (`aiarmada/products`)
Bugs:
- 10 models `owner_type/id` fillable MEDIUM (was CRITICAL) — fact true, but `Product::booted` creating/updating + `Variant::creating` throw on cross-tenant; defense-in-depth.
- `Models/Variant.php` HIGH — no `updating` guard; `product_id` reassignable post-create.
- `Models/Product.php:813-820` MEDIUM — `deleting` `each(delete)` + detaches, no txn/chunk.
- `UpdateProductStatus.php:65-68` LOW — `transitionToDraft` leaves stale `published_at`.
Security:
- `Variant` no `updating` guard HIGH (above) — security impact: `product_id/owner_*` reassignment post-create.
- Filament `ProductResource:40-44` LOW — `Product::query()->forOwner()` bypasses `parent::getEloquentQuery()`; use `OwnerUiScope::apply(parent::...)`.
Performance:
- Variant cascade 1k+ deletes MEDIUM — chunk or queue.
- `Collection:453` / `Category:229` strip-then-refilter LOW (was MEDIUM) — correctly re-scoped; style risk only.

#### Verdicts: products

- [P1] CONFIRMED high bug | src/Models/Product.php:817 | options()->delete() mass-deletes, skipping Option::deleting; Option:219 mass values()->delete() skips OptionValue:248 detach; orphans values+pivots
- [P2] CONFIRMED high bug | src/Strategies/MatrixVariantGenerator.php:95 | SKU dedup forOwner-scoped only, no product_id constraint; shared parent SKU attaches sibling product variant
- [P3] CONFIRMED high bug | database/migrations/2001_01_01_000001_create_products_table.php:84 | identity uniques env-gated to local/dev/test; prod has no slug/sku unique+index: TOCTOU dupes, full scans
- [P4] CONFIRMED high bug | src/Models/Category.php:308 | getAncestors while-loop has no visited set; booted() validates owner only, no parent self/cycle/owner check; A-B cycle hangs
- [P5] CONFIRMED medium bug | src/Actions/CreateProduct.php:20 | $dispatchesEvents created/updated (Product:156) plus explicit dispatch in Create/UpdateProduct; subscribers run twice
- [P6] CONFIRMED medium security | src/Models/Collection.php:437 | default branch where($field,$operator,$value) from fillable conditions JSON, no allowlist; grammar-escaped, owner-scoped
- [P7] CONFIRMED medium performance | src/Models/Collection.php:245 | getMatchingProducts unbounded get() + rebuildProductList sync() of full id set; needs chunk/cursor + batched sync
- [P8] CONFIRMED medium bug | src/Actions/ApplyAttributeChanges.php:47 | raw update(['value']) bypasses AttributeType::serializeValue; unknown codes skipped; fresh() nullable vs Product return
- [P9] CONFIRMED medium security | src/Models/Product.php:438 | currency fillable (:134), no allowlist/validation; dynamic Money::$currency in getPriceAsMoney/formatMinorAmount throws on 'XX'
- [P10] CONFIRMED medium security | src/Models/AttributeValue.php:189 | attributable_type accepts any Model class; targets without belongsToOwner skip checks (:202); junk rows + id oracle
- [P11] CONFIRMED medium performance | src/Models/Variant.php:237 | per-call product/optionValues queries (display/price/summary/fullname); getStockQuantity fans out per variant; getProductCount recurses
- [P12] CONFIRMED low bug | src/Models/Variant.php:384 | catch(Throwable){return 0} reports outage as out-of-stock; Product:709 falls back to local stock instead; inconsistent
- [P13] CONFIRMED low bug | src/Concerns/EnforcesOwnerUniqueIdentity.php:68 | app-level probe is racy; fabricates UniqueConstraintViolationException with synthetic SQL on create; moot once P3 fixed
- [P14] CONFIRMED low performance | database/factories/ProductFactory.php:53 | Schema::getColumnListing per definition call; cache column list statically per process
- [P15] CONFIRMED low security | src/Jobs/GenerateVariantsJob.php:31 | withoutOwnerScope()->whereKey(productId) never matched to job owner context; fail-closed via creating guards = retry nuisance
- [P16] CONFIRMED low security | src/Models/OptionValue.php:167 | swatch_color/image interpolated into CSS unvalidated; stored breakout if rendered raw (no raw-render site found)
- [AUD:B1] ADOPTED medium security | src/Models/Product.php:120 | 10 models keep owner_type/id fillable; creating/updating guards mitigate; defense-in-depth, adopt audit severity
- [AUD:B2] ADOPTED high bug | src/Models/Variant.php:465 | booted() has creating guard only, no updating guard; product_id/owner_* reassignable post-create
- [AUD:B3] ADOPTED medium bug | src/Models/Product.php:813 | deleting each(delete)+detaches with no txn/chunk; overlaps P1 area (P1 covers the event-skip orphaning aspect)
- [AUD:B4] ADOPTED low bug | src/Actions/UpdateProductStatus.php:65 | transitionToDraft clears archived/deactivated but leaves stale published_at
- [AUD:B5] ADOPTED high security | src/Models/Variant.php:465 | DUP AUD:B2 security facet: post-create product_id/owner_* reassignment; counted once
- [AUD:B6] ADOPTED low bug | filament-products/src/Resources/ProductResource.php:40 | Product::query()->forOwner() bypasses parent::getEloquentQuery(); use OwnerUiScope::apply(parent::...)
- [AUD:B7] ADOPTED medium performance | src/Models/Product.php:813 | DUP AUD:B3 perf facet: cascade variant deletes unchunked/unqueued; counted once
- [AUD:B8] ADOPTED low performance | src/Models/Collection.php:453 | withoutOwnerScope then OwnerQuery re-apply (Category:229 same); correctly re-scoped, style risk only

### Prior-audit chunk: filament-pricing

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-pricing` GOOD — owner-gated + `OwnerQuery` resolution + delegates to `PriceCalculatorInterface`.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-pricing

- [F1] FALSE high security | src/Widgets/PricingStatsWidget.php:19 | PriceList HasOwner adds automatic global OwnerScope; pricing.features.owner defaults false so global-by-design; scoped or global, no leak
- [F2] CONFIRMED high security | src/Resources/PriceListResource/RelationManagers/PricesRelationManager.php:112 | getOptionLabelUsing accepts any Model class (Tiers:118 allowlists); Create/Edit header actions lack server revalidation
- [F3] CONFIRMED high security | src/Pages/ManagePricingSettings.php:154 | save() reads raw $this->data, never getState (decimalPlaces 0-4 unenforced); no canAccess; Spatie settings global
- [F4] CONFIRMED medium security | src/Pages/PriceSimulator.php:346 | calculate() uses raw $this->data; direct $data['product_type']/(int)$data['quantity']; form rules bypassable via direct Livewire call
- [F5] DOWNGRADED (was medium) low security | src/Resources/PriceListResource/Schemas/PriceListForm.php:41 | slug unique() mirrors schema global unique; pricing global-by-default; owner-scoped rule needs schema change first
- [F6] CONFIRMED low security | src/Pages/PriceSimulator.php:119 | unescaped %{$search}% LIKE at sim 119/183/286, Prices 69/92, Tiers 75/98; %/_ broaden matches
- [F7] CONFIRMED low performance | src/Resources/PriceListResource/RelationManagers/TiersRelationManager.php:217 | tierable_label state closure loadMissing tierable+product per row; eager-load in table query
- [F8] CONFIRMED low bug | src/Resources/PriceListResource/RelationManagers/PricesRelationManager.php:97 | $v->product->name null-unsafe on orphaned variants (also :134, Tiers:103,139); simulator uses ?-> (:203)
- [F9] CONFIRMED low security | src/ | zero Policy/Gate matches in filament-pricing/src and pricing/src; PriceList/Price/Tier CRUD relies on Filament defaults
- [AUD:B1] ADOPTED - note | src/ | prior-audit GOOD note: owner-gated + OwnerQuery resolution + PriceCalculatorInterface delegation; no action
- [AUD:B2] ADOPTED - note | src/Pages/ManagePricingSettings.php:31 | G1 PASS: getNavigationGroup/sort config-driven, no static $navigationGroup; compliant
- [AUD:B3] ADOPTED medium performance | src/ | G2 N/A here: no getNavigationBadge in pkg; global uncached-COUNT note applies to badged resources elsewhere
- [AUD:B4] ADOPTED medium performance | src/Resources/PriceListResource/RelationManagers/PricesRelationManager.php:185 | G3: priceable.name TextColumn without eager-load in table query; Tiers uses per-row loadMissing (see F7)
- [AUD:B5] ADOPTED medium performance | src/ | G4 N/A here: no Model::pluck()->all() preload selects found
- [AUD:B6] ADOPTED low bug | src/ | G5 N/A here: no domain-leakage helpers; MoneyFormatter is shared commerce-support
- [AUD:B7] ADOPTED medium performance | src/ | G6 N/A here: no get()->sum(fn) collection sums found

### Prior-audit chunk: references

### Prior-audit section
### references
Bugs:
- `Models/Reference.php:95-122,240-257` MEDIUM — level-at-a-time `pluck` + per-media `delete`, bulk `delete()` skips child events (in txn, correct).
- No `transitionStatus()` LOW — `Published` without `published_at` persists silently.
Security:
- Migration `slug unique` MEDIUM (was HIGH) — `create_references_table:20 unique(slug)` + `nullableUuidMorphs('owner')` + `HasOwner`. Blocks reuse + enumeration-if-exposed; fix: `(owner_type,owner_id,slug)` composite.
- `$fillable slug/parent_id/is_canonical` MEDIUM — squatting + multiple canonicals; parent guarded, canonical unguarded.
Performance: `collectSubtreeIds` level-at-a-time LOW-MEDIUM — recursive CTE or `withCount` if deep.

#### Verdicts: references

- [R1] DOWNGRADED (was high) medium security | database/migrations/2000_01_01_000001_create_references_table.php:21 | DUP AUD:B3; global slug unique squats/blocks cross-tenant + oracle; adopt audit medium
- [R2] CONFIRMED medium bug | database/migrations/2000_01_01_000001_create_references_table.php:9 | migration class has up() only, no down(); rollback leaves table behind
- [R3] CONFIRMED medium bug | src/Models/Reference.php:95 | DUP AUD:B1; descendants mass-deleted w/o child events; media get()->each unbounded; scoped subtree collect; no self/cycle check
- [R4] CONFIRMED medium security | src/ReferencesServiceProvider.php:1 | no Policy/Gate registration for Reference anywhere in pkg; auth left to consumers
- [R5] CONFIRMED low security | src/Models/Reference.php:59 | unbounded reference_parts/metadata JSON, unvalidated year/isbn/url/language; no Action layer in pkg
- [R6] CONFIRMED low bug | src/Models/Reference.php:124 | getTable default 'ref_references' vs config default 'references' (config:12); stale fallback when unloaded
- [R7] CONFIRMED low bug | src/Models/Reference.php:48 | Reference omits HasReferenceParts trait (exists src/Traits/); helpers only exercised on stub model; DX gap
- [AUD:B1] ADOPTED medium bug | src/Models/Reference.php:95 | DUP R3; txn-wrapped level-at-a-time pluck + per-media delete; bulk delete skips child events; counted once
- [AUD:B2] ADOPTED low bug | src/Models/Reference.php:202 | no transitionStatus(); Published without published_at persists silently (only query scopes exist)
- [AUD:B3] ADOPTED medium security | database/migrations/2000_01_01_000001_create_references_table.php:21 | DUP R1; unique(slug) + nullableUuidMorphs owner; fix: (owner_type,owner_id,slug); counted once
- [AUD:B4] ADOPTED medium security | src/Models/Reference.php:59 | fillable slug/parent_id/is_canonical; parent guarded (:80, owner-enabled only), is_canonical unguarded; squat/multi-canonical
- [AUD:B5] ADOPTED low performance | src/Models/Reference.php:235 | DUP R3(c); collectSubtreeIds level-at-a-time pluck; recursive CTE if trees deepen; counted once

---

## Round 2 — `filament-products`, `organizations`, `filament-seating`, `filament-persons`

### E2E findings (verbatim)

End-to-end review: filament-products, organizations, filament-seating, filament-persons. All findings below were verified by reading the cited file bodies (plus referenced domain models in products/seating/persons/pricing and commerce-support owner primitives). No routes/ or tests/ directories exist in any of the four packages (verified by scoped search returning zero matches).

## filament-products

1. severity: high | category: bug | packages/filament-products/src/Resources/CategoryResource/Pages/EditCategory.php:25 — Self-parent / hierarchy cycle allowed, causes infinite loop
Description: mutateFormDataBeforeSave only checks the submitted parent_id is owner-scoped; nothing rejects parent_id == the record's own id or a descendant's id. CreateCategory (same, :17) has the same gap. products Category::getAncestors() (packages/products/src/Models/Category.php:303) walks `while ($category->parent !== null)` with no cycle guard, so a self-parent loops forever (hung PHP/Octane worker). Every row of the category table triggers this via getDepth() (CategoriesTable.php:33-38).
Evidence: `if (isset($data['parent_id']) && is_string(...)) { $allowed = OwnerScopedIds::allowedIds(...); if ($allowed === []) unset(...); }` — no self/descendant check.
Recommendation: reject parent_id equal to the record id and any current descendant (Filament `different:`/custom rule + server-side check in both pages); add a defense-in-depth cycle guard in products Category model (domain owner).
Confidence: high.

2. severity: medium | category: bug/performance | packages/filament-products/src/Resources/ProductResource/Tables/ProductsTable.php:441 — CSV import unbounded, synchronous, silently coerces bad data
Description: FileUpload has no maxSize (:147-153); importProducts has no row cap, runs synchronously in the request, does one SKU lookup + save per row with no batching/queue/transaction. Invalid enums silently coerce (`ProductStatus::tryFrom(...) ?? Draft`, :484-486), missing price defaults to 0 (:480) — corrupt CSV rows create free/draft products instead of erroring.
Evidence: `$csv = Reader::createFromPath($filePath, 'r'); ... foreach ($records as $offset => $record)` with per-row `Product::query()->forOwner($owner, false)->where('sku', ...)->first()` then `->save()`.
Recommendation: add maxSize + max row limit, queue/chunk the import, wrap batches in transactions, and surface invalid enum/currency/price cells as row errors instead of defaulting.
Confidence: high.

3. severity: medium | category: performance | packages/filament-products/src/Resources/ProductResource/Tables/ProductsTable.php:545 — Export loads entire catalog into memory; streaming is fake
Description: `$query->get()` materializes every product, then `Writer::createFromString()` builds the whole CSV string before `streamDownload` echoes it — OOM risk on large catalogs despite the StreamedResponse wrapper.
Evidence: `$products = $query->get(); $csv = Writer::createFromString(); ... echo $csv->toString();`
Recommendation: cursor/chunk the query and stream rows incrementally (`Writer::createFromPath('php://output')` or chunked echo with flush).
Confidence: high.

4. severity: medium | category: security | packages/filament-products/src/Resources/ProductResource/Schemas/ProductForm.php:53 — Global unique() checks leak cross-owner slugs/SKUs and allow squatting
Description: `TextInput slug/ska ->unique(ignoreRecord: true)` (also CategoryForm.php:42, AttributeSetForm.php:27, VariantsRelationManager.php:47) issues table-level uniqueness checks that ignore owner scope, while the domain enforces uniqueness per-owner (Product::EnforcesOwnerUniqueIdentity on slug/sku). Effects: another owner's slug/SKU blocks creation (availability), and "already taken" responses let one tenant enumerate other tenants' slugs/SKUs.
Recommendation: scope the unique rule to the current owner tuple (modifyRuleUsing / Rule::unique()->where(owner_type, owner_id)).
Confidence: med (high that the check is global; impact depends on Filament unique-rule internals, which are table-based).

5. severity: medium | category: security | packages/filament-products/src/Resources/ProductResource/RelationManagers/PricesRelationManager.php:39 — price_list_id never revalidated; cross-owner linkage possible
Description: PriceList is HasOwner (verified), the create/edit Select has no owner scoping and neither CreateAction (which only sets priceable_type/id, :170-178) nor EditAction calls OwnerScopedIds — a tampered price_list_id links a product to another owner's price list, unlike every other ID field in this package.
Recommendation: add `OwnerScopedIds::ensureAllowed('price_list_id', PriceList::class, ...)` in mutateFormDataUsing for create and edit, and scope the relationship select.
Confidence: high.

6. severity: medium | category: performance | packages/filament-products/src/Widgets/TopSellingProductsWidget.php:70 — Per-row queries (N+1) in tables/widgets
Description: variants_count column calls getVariantsCount() per row (:70-73, :91-97: `$record->variants()->count()`); ProductsTable price description does 2 counts per row (:86-102); CategoriesTable name formatter walks ancestors per row (:33-38, each level a lazy query).
Recommendation: withCount('variants'), eager price counts, and eager-load/cache category depth (or denormalize depth).
Confidence: high.

7. severity: medium | category: security | packages/filament-products/src/Resources/CategoryResource.php:1 — Only ProductResource has authorization; sibling resources and bulk actions have none
Description: ProductResource defines canViewAny/canView/canCreate/canEdit/canDelete + shouldRegisterNavigation via FilamentPermission, but Category/Collection/Attribute/AttributeGroup/AttributeSet resources define zero can* methods, and CategoriesTable bulk show/hide/delete (:112-131) carry no authorize() calls. Any panel user can mutate catalog taxonomy.
Recommendation: mirror ProductResource's FilamentPermission gates (or policies) on all resources and authorize bulk actions.
Confidence: med (host panel may layer its own auth, but the package is inconsistent with its own pattern).

8. severity: low | category: bug | packages/filament-products/src/Resources/ProductResource/Tables/ProductsTable.php:256 — Duplicate uses time() slug and copies only the shell
Description: `'slug . '-copy-' . time()` collides if duplicated twice in one second (then surfaces as a save exception); variants/options/prices/media/categories are not copied despite the "Duplicate" label.
Recommendation: loop a unique slug (or reuse domain slug logic) and either copy relations or rename the action to "Duplicate as new shell".
Confidence: high.

9. severity: low | category: bug | packages/filament-products/src/Resources/ProductResource/Tables/ProductsTable.php:336 — Bulk price update: float math, no transaction, partial failure
Description: per-record `$product->price / 100` float round-trip with individual updates outside a transaction — a mid-batch failure leaves half the selection repriced; `$data['value']` has no upper bound.
Recommendation: integer minor-unit math, wrap in a transaction, cap value.
Confidence: high.

10. severity: low | category: bug | packages/filament-products/src/Support/ProductStatsAggregator.php:52 — Widgets silently degrade to global-only stats when owner context is missing
Description: withResolvedOwnerOrExplicitGlobal() wraps every widget query in OwnerContext::withOwner(null) when unresolved, so a misconfigured tenant dashboard shows global-record stats instead of failing closed. Not a cross-tenant leak (forOwner(null) scopes to ownerless rows), but masks configuration errors.
Recommendation: fail closed (or show an explicit "no owner context" state) instead of silent global fallback.
Confidence: high.

## organizations

11. severity: medium | category: bug | packages/organizations/src/Actions/CreateOrganizationAction.php:29 — No input length/type validation; oversize input 500s
Description: handle() only mb_trims name and checks non-empty; name >255 chars or a non-string description blows up as a raw QueryException instead of a validation error.
Recommendation: validate name (required, string, max:255), slug format, description nullable|string before the transaction.
Confidence: high.

12. severity: medium | category: bug | packages/organizations/database/migrations/2000_01_01_000001_create_organizations_table.php:1 — Migrations have no down(); rollback/refresh broken
Description: both migrations define only up(). migrate:rollback is a silent no-op and migrate:refresh leaves tables behind so re-migration fails with "table exists".
Recommendation: add down() dropping the configured table names.
Confidence: high.

13. severity: medium | category: bug | packages/organizations/src/Actions/MakeOrganizationPublicAction.php:25 — No state-machine guards; restore can silently re-publish
Description: a suspended/archived org can be made Public (published_at set while inactive), and RestoreOrganizationAction (:25-38) flips status to Active without any visibility review — restoring a suspended+public org re-publishes it with no visibility transition audit/authorization.
Recommendation: block visibility transitions unless Active (or require explicit visibility re-confirmation on restore).
Confidence: high.

14. severity: low | category: security | packages/organizations/src/Resolvers/DefaultOrganizationAuthorization.php:30 — Unknown abilities default-allow for Owner/Admin
Description: `default => in_array($role, [Owner, Admin])` fails open for any future/typo'd ability string. Prefer fail-closed (deny unknown).
Confidence: high.

15. severity: low | category: bug | packages/organizations/src/Actions/TransferOrganizationOwnershipAction.php:44 — New-owner model type not verified
Description: the target lookup matches only by key (`whereKey($newOwner->getKey())`); a different model class with a colliding key would pass the membership check.
Recommendation: assert the new owner is the expected member model type (and same morph class).
Confidence: med.

16. severity: low | category: security | packages/organizations/src/Models/Organization.php:48 — $fillable includes status/visibility/lifecycle timestamps
Description: within this package CreateOrganizationAction allowlists its fields, so no direct exploit here; but fillable status/visibility/created_by invites host-code mass-assignment (`Organization::create($request->all())`).
Recommendation: narrow $fillable and use explicit assignment/forceFill in actions.
Confidence: high (on the surface; low on exploitability in-package).

## filament-seating

17. severity: medium | category: bug | packages/filament-seating/src/Widgets/SeatMapOverview.php:15 — Widget throws (dashboard 500) with no owner context
Description: `SeatMapModel::count() / Seat::count()` rely on the implicit OwnerScope global scope, which throws NoCurrentOwnerException when no owner is resolved (verified in OwnerScope.php:23). No try/catch, no forOwner(), unlike the products widgets. (Not a leak — the scope applies — but an availability bug.)
Recommendation: resolve the owner explicitly and use forOwner()/OwnerUiScope like SeatMapResource::getEloquentQuery does.
Confidence: high.

18. severity: medium | category: security | packages/filament-seating/src/Resources/SeatMapResource.php:1 — No authorization on the resource
Description: no can* methods, no policy checks on view/edit actions; any authenticated panel user can read/edit all seat maps in scope. Inconsistent with ProductResource's FilamentPermission pattern.
Recommendation: add canViewAny/canView/canCreate/canEdit/canDelete gates.
Confidence: med (host-dependent, pattern-inconsistent).

19. severity: medium | category: bug | packages/filament-seating/src/Pages/SeatMapEditor.php:19 — Editor/occupancy pages are dead yet registered; seatMapId unvalidated
Description: canAccess() returns false on both pages while the plugin still registers them; mount(?string $seatMapId) accepts any id with zero owner/existence validation and passes it to the Livewire seat-map component — an IDOR waiting to happen if the pages are ever enabled.
Recommendation: either remove the pages or implement canAccess + owner-scoped seatMapId resolution (OwnerUiScope::findForRecordOwner).
Confidence: high.

20. severity: low | category: bug | packages/filament-seating/src/Resources/SeatMapResource.php:48 — Weak form validation; status not enum-bound
Description: slug optional and non-unique, version numeric with no min (negatives allowed), status hardcoded strings while the model stores a plain string — drift-prone.
Recommendation: require/unique-scope slug, minValue(1) on version, back status by an enum with casts.
Confidence: high.

## filament-persons

21. severity: medium | category: bug | packages/filament-persons/src/Resources/PersonResource/RelationManagers/TitleAssignmentsRelationManager.php:79 — All relation-manager EditActions have empty forms
Description: Title/Credential/Names/Affiliations managers define ->form([...]) only on CreateAction; EditAction::make() is bare and no form(Schema) method exists, so every Edit modal renders with no fields — assignments cannot be edited. (CredentialAssignments :72, Names :65, Affiliations :94 identical.)
Recommendation: extract shared form schemas used by both create and edit actions.
Confidence: high.

22. severity: medium | category: bug | packages/filament-persons/src/Resources/PersonResource/RelationManagers/AffiliationsRelationManager.php:30 — Institution selection is a non-functional stub
Description: getInstitutionOptions() hardcoded to `[]`, so the institution Select is always empty; the table falls back to rendering raw institution ids.
Recommendation: resolve real options from ModelResolver::institutionClass() (owner-aware where applicable) or remove the field until implemented.
Confidence: high.

23. severity: medium | category: security | packages/filament-persons/src/Resources/PersonResource.php:1 — No authorization on global identity/PII resources
Description: Person/Title/TitleIssuer/CredentialDefinition resources define no can* gates; persons are deliberately global (names, DOB, biographies) so every panel user gets full read/write on shared identity data.
Recommendation: add FilamentPermission/policy gates consistent with filament-products.
Confidence: med (host-dependent, but PII-adjacent globals deserve in-package gates).

24. severity: low | category: bug | packages/filament-persons/src/Resources/PersonResource/RelationManagers/TitleAssignmentsRelationManager.php:60 — Assignment validation gaps; inconsistent institution guard
Description: create only findOrFail()s the foreign id — no date_awarded<=date_expired check, no duplicate (person,title/credential) guard; TitleIssuerResource institution_id (:65-76) is free text with no PersonsModelReferenceGuard check even though Affiliations create (:62-74) does guard it.
Recommendation: add date-order + uniqueness validation and guard institution_id consistently.
Confidence: med.

25. severity: low | category: performance | packages/filament-persons/src/Resources/TitleResource.php:78 — Sort-order helper queries per form render; null-category logic wrong
Description: the live helperText closure runs an exists() query on every re-render, and `->where('category_id', null)` never matches (should be whereNull), so guidance is wrong until a category is picked.
Recommendation: skip the query when category/position are blank and debounce/cache.
Confidence: high.

26. severity: low | category: performance | packages/filament-persons/src/Resources/PersonResource/RelationManagers/NamesRelationManager.php:56 — Uncached language list per form render
Description: `Language::query()->orderBy('name')->pluck(...)` executes on every Names form open with no caching.
Recommendation: cache the small languages map with a TTL.
Confidence: high.

## Cross-cutting

27. severity: medium | category: bug | packages/{filament-products,organizations,filament-seating,filament-persons} — Zero tests in all four packages
Description: verified by scoped search — no tests/ directories or Pest suites anywhere in these packages, so none of the above (owner scoping, transfers, imports) is regression-protected.
Recommendation: add Pest coverage at minimum for owner-scope enforcement, organization transfer invariants, and CSV import/export round-trips.
Confidence: high.

## Positives (brief)
- Owner scoping in filament-products is thorough: forOwner() on every resource query, OwnerScopedIds::ensureAllowed in all Create/Edit pages, OwnerQuery scoping on selects/filters/bulk category assignment, OwnerUiScope on SeatMapResource.
- Organizations invariants are solid: exactly-one-owner enforced transactionally with lockForUpdate, immutable created_by, race-safe slug retry, fail-closed middleware + resolver boot check.
- CSV path traversal guarded (imports/ prefix + Storage::exists); no SSRF/deserialization/XSS sinks found (no Http/file_get_contents/unserialize/eval/RawHtml in these packages); stats cache invalidation correctly wired via model events; money consistently int minor units; uuid PKs, no FK constraints, no SoftDeletes, no static mutable state (Octane-safe).

### Prior-audit chunk: filament-products

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-products` MEDIUM — writes exemplary (`OwnerScopedIds` create/edit + bulk). `ProductsTable:91,99` per-row `prices()->count()` N+1 + CSV import category re-check gap.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-products

- [R1:#1] CONFIRMED high bug | EditCategory.php:25 | "Self-parent hierarchy cycle" no self/descendant check; Category.getAncestors:308 loops forever
- [R1:#2] CONFIRMED medium bug/performance | ProductsTable.php:441 | "Unbounded CSV import" no maxSize/row cap, sync per-row save; bad enums default Draft/0
- [R1:#3] CONFIRMED medium performance | ProductsTable.php:545 | "Fake streaming export" get()+createFromString materialize all rows; OOM risk stands
- [R1:#4] CONFIRMED medium security | ProductForm.php:53 | "Global unique checks" table-level unique vs per-owner domain rule; squat/enumeration surface stands
- [R1:#5] DOWNGRADED (was medium) low security | PricesRelationManager.php:39 | "Unvalidated price list" Price::saving:141 scoped-exists blocks x-owner; filament gap remains
- [R1:#6] CONFIRMED medium performance | TopSellingProductsWidget.php:70 | "Per-row query counts" variants/prices/getDepth per row; DUP AUD:B1a same sev
- [R1:#7] CONFIRMED medium security | CategoryResource.php:1 | "Missing resource authorization" zero can* in siblings/base; bulk show/hide/delete unauthorised
- [R1:#8] CONFIRMED low bug | ProductsTable.php:256 | "Duplicate shell only" time() slug can collide; relations not copied
- [R1:#9] CONFIRMED low bug | ProductsTable.php:336 | "Bulk price update" float round-trip, no txn/partial failure; minValue only, no upper bound
- [R1:#10] CONFIRMED low bug | ProductStatsAggregator.php:52 | "Silent global fallback" unresolved owner shows global stats, masks misconfig
- [AUD:B1a] ADOPTED medium performance | ProductsTable.php:91 | DUP R1:#6, counted once; per-row prices()->count() still present
- [AUD:B1b] FALSE - bug | ProductsTable.php:441 | import:441-543 has no category path at all; described re-check gap cannot exist

### Prior-audit chunk: organizations

### Prior-audit section
### organizations (org IS owner, no HasOwner — correct)
Bugs:
- `Models/Organization.php:148-171` MEDIUM — `transitionToStatus/Visibility` never clears stale counterpart (`suspended_at/archived_at/published_at`).
- `Models/Organization.php:48-60` MEDIUM — `status/visibility/*_at/created_by` fillable bypasses transitions (only `created_by` guarded in `saving`).
Security: clean — `TransferOrganizationOwnershipAction` txn + lock + single-owner invariant.
Performance: clean, indexes present.

#### Verdicts: organizations

- [R1:#11] CONFIRMED medium bug | CreateOrganizationAction.php:29 | "Missing input validation" only trim+non-empty; oversize input 500s via QueryException
- [R1:#12] CONFIRMED medium bug | 2000_01_01_000001_create_organizations_table.php:1 | "Migrations lack down" both migrations up-only; rollback no-op, refresh breaks
- [R1:#13] CONFIRMED medium bug | MakeOrganizationPublicAction.php:25 | "No status guards" public-while-inactive allowed; restore re-publishes w/o review
- [R1:#14] CONFIRMED low security | DefaultOrganizationAuthorization.php:30 | "Unknown abilities allowed" default arm allows Owner/Admin; fails open
- [R1:#15] CONFIRMED low bug | TransferOrganizationOwnershipAction.php:44 | "Unchecked owner type" whereKey-only lookup; key-equivalent so impact negligible
- [R1:#16] CONFIRMED medium bug | Organization.php:48 | "Fillable lifecycle fields" DUP AUD:B2 adopted medium (was low); host mass-assign surface
- [AUD:B1] ADOPTED medium bug | Organization.php:148 | stale counterparts never cleared on transitions; related R1:#13 but distinct aspect
- [AUD:B2] ADOPTED medium bug | Organization.php:48 | DUP R1:#16, counted once; fillable bypasses transitions

### Prior-audit chunk: filament-seating

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-seating` GOOD — scoped + `withCount(sections)`.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-seating

- [R1:#17] CONFIRMED medium bug | SeatMapOverview.php:15 | "Widget throws ownerless" bare count() hits OwnerScope throw; seating owner on by default
- [R1:#18] CONFIRMED medium security | SeatMapResource.php:1 | "No resource authorization" zero can* anywhere in pkg; host-dependent caveat kept
- [R1:#19] CONFIRMED medium bug | SeatMapEditor.php:19 | "Dead registered pages" canAccess false yet plugin-registered; seatMapId unvalidated IDOR-ready
- [R1:#20] CONFIRMED low bug | SeatMapResource.php:48 | "Weak form validation" slug optional, version no min, status strings vs plain-string model
- [AUD:B1] ADOPTED none info | SeatMapResource.php:42 | PASS confirmed: OwnerUiScope + withCount(sections)

### Prior-audit chunk: filament-persons

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-persons` — bare `with()` correct (Person explicitly unscoped shared identity per `filament-persons/CONTEXT.md`); relation selects no `modifyQueryUsing` MEDIUM (enumeration scope only).
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-persons

- [R1:#21] CONFIRMED medium bug | TitleAssignmentsRelationManager.php:79 | "Empty edit forms" all 4 managers bare EditAction, no form(Schema); edits impossible
- [R1:#22] CONFIRMED medium bug | AffiliationsRelationManager.php:30 | "Institution stub empty" getInstitutionOptions hardcoded []; table shows raw ids
- [R1:#23] CONFIRMED medium security | PersonResource.php:1 | "No PII authorization" zero can* on global identity resources; host-dependent caveat kept
- [R1:#24] CONFIRMED low bug | TitleAssignmentsRelationManager.php:60 | "Assignment validation gaps" no date/dup checks; issuer institution unguarded vs affiliations
- [R1:#25] CONFIRMED low performance | TitleResource.php:78 | "Sort helper queries" exists() per render; never-matches claim wrong (whereNull coercion)
- [R1:#26] CONFIRMED low performance | NamesRelationManager.php:56 | "Uncached language list" pluck per form render, no TTL; overlaps AUD:G4
- [AUD:B1] ADOPTED medium security | TitleAssignmentsRelationManager.php:61 | relation selects lack modifyQueryUsing (enum scope); bare with() correct per CONTEXT

---

## Round 2 — `filament-promotions`, `inventory`, `vouchers`, `filament-engagement`

### E2E findings (verbatim)

End-to-end review: filament-promotions, inventory, vouchers, filament-engagement

FINDINGS (file paths relative to /Users/Saiffil/Herd/commerce)

1. severity=high category=security file=packages/vouchers/src/Actions/UpdateVoucher.php:25 title=UpdateVoucher query is not owner-scoped — cross-owner update. Description: handle() looks up the voucher with a bare VoucherModel::query()->where('code',...), unlike every other voucher action which uses QueriesVouchers::voucherQuery() (owner scope + withCount). Any caller in owner-A context can update owner-B's voucher by code. Evidence: `$voucher = VoucherModel::query()->where('code', $normalizedCode)->firstOrFail();` (contrast RecordVoucherUsage/ExpireVoucher/AddVoucherToWallet which use $this->voucherQuery()). Recommendation: use $this->voucherQuery() (add QueriesVouchers trait) so HasOwner global scope applies. Confidence: high.

2. severity=high category=security file=packages/vouchers/src/Actions/UpdateVoucher.php:38 title=UpdateVoucher mass-assigns owner/applied_count/code. Description: after only affiliate sanitization, $voucher->update($data) writes arbitrary keys; Voucher::$fillable (Models/Voucher.php:99-134) includes owner_type, owner_id, applied_count, code, status timestamps. A caller passing user input can hijack ownership or corrupt counters. Evidence: `$data = VoucherAffiliateOwnershipGuard::sanitize($data); ... $voucher->update($data);`. Recommendation: allowlist updatable fields (exclude code/owner_*/applied_count/usages counters) and route owner changes through OwnerWriteGuard. Confidence: high.

3. severity=high category=bug file=packages/vouchers/src/Actions/CreateVoucher.php:47-48 title=CreateVoucher has no validation; required keys fatal, ranges unchecked. Description: $data['type']/$data['value'] accessed directly (undefined-index 500 on missing keys); no checks for negative value, percentage >10000bp, negative limits, bad currency/date strings, min>max. Evidence: `'type' => $data['type'], 'value' => $data['value'],`. Recommendation: validate via VoucherData::fromArray + range checks (value>=0, percentage<=10000, limits>=0, currency alpha-3, starts_at<expires_at) before create. Confidence: high.

4. severity=high category=security file=packages/filament-promotions/src/Actions/IssuePromotionVouchersAction.php:55 title=Issue-vouchers count cap enforced only in UI; server accepts unbounded count. Description: form maxValue(100) is client-side; handler does max(1,(int)$data['count']) with no upper bound, so a crafted request can mass-create vouchers (DoS + economic abuse). Same flaw in IssuePromotionVouchersFromListAction.php:58. Evidence: `$count = max(1, (int) ($data['count'] ?? 1));`. Recommendation: clamp server-side, e.g. min(max(1,$count),100). Confidence: high.

5. severity=high category=security file=packages/filament-promotions/src/Actions/IssuePromotionVouchersFromListAction.php:91 title=promotionOptions() enumerates ALL promotions unscoped and unbounded. Description: Select options load every Promotion row (id/name/code) with no owner scope and no limit — cross-owner name/code disclosure + full-table load. The later resolvePromotion() is correctly guarded, so writes are safe but reads leak. Evidence: `return Promotion::query()->orderBy('name')->get(['id','name','code'])->mapWithKeys(...)`. Recommendation: scope via OwnerUiScope/OwnerWriteGuard-compatible query and cap/search-server-side. Confidence: high. (Also performance.)

6. severity=high category=security file=packages/filament-promotions/src/Widgets/PromotionStatsWidget.php:18 (root cause packages/promotions/src/Support/PromotionPerformanceInsights.php:114-123,203-206) title=Promotion widgets aggregate cross-owner data and load entire orders table. Description: overview()/topPromotions*() use unscoped Promotion::query() (promotions() returns bare query; $scoped=$query no-op) and Order::query()->count() + ->get() over ALL orders with PHP-side metadata parsing — cross-owner revenue/usage leak plus unbounded O(orders) load on every widget render (also TopPromotionsUsageChart). Evidence: `private function promotions(): Builder { $query = Promotion::query(); $scoped = $query; return $scoped; }` and `$orders = Order::query()->select([...])->get();`. Recommendation: owner-scope promotion/order queries (or pass owner into insights), paginate/chunk + cache; never full-table get() in a widget. Confidence: high. (Also performance critical.)

7. severity=high category=security file=packages/inventory/src/Models/InventoryLocation.php:133 title=getOrCreateDefault() shares one DEFAULT location across owners. Description: asserts owner context but then firstOrCreate(['code'=>DEFAULT]) on an unscoped query with no owner attributes — all owners get the first owner's row (cross-owner stock mixing). Evidence: `return self::firstOrCreate(['code' => self::DEFAULT_LOCATION_CODE], [...]);`. Recommendation: include owner tuple in lookup+create (or per-owner code unique), keep assertion. Confidence: high.

8. severity=high category=security file=packages/inventory/src/Models/InventoryReorderSuggestion.php:136 (same pattern InventoryBackorder.php:115, InventoryDemandHistory.php:83) title=Saving hooks validate location via unscoped lookup and adopt any owner's tuple. Description: hooks do InventoryLocation::query()->find($location_id) without InventoryOwnerScope, then copy that location's owner_type/id onto the row without comparing to the resolved $owner — a caller in owner-A context can file suggestions/backorders/demand under owner-B by passing B's location id. Evidence (reorder): `$location = InventoryLocation::query()->select(['id','owner_type','owner_id'])->find($suggestion->location_id); ... $suggestion->owner_type = $location->owner_type;`. Recommendation: use InventoryOwnerScope::applyToLocationQuery(...) for the lookup like Level/Allocation/Movement do. Confidence: high.

9. severity=medium category=security file=packages/inventory/src/Services/Stock/CheckoutReservationService.php:320 title=Reservation lines resolve arbitrary Eloquent classes from caller-supplied morph type. Description: resolveInventoryModel() takes $line->inventoryableType, runs Relation::getMorphedModel() ?? raw string, class_exists + ::find() on ANY Model subclass — allows probing/instantiating models outside the inventoryable contract (allocation then uses its morph class/key). Evidence: `$inventoryableClass = Relation::getMorphedModel($line->inventoryableType) ?? $line->inventoryableType; if (class_exists(...) && is_a(..., Model::class, true))`. Recommendation: allowlist to inventoryable types (product/variant config + InventoryableInterface). Confidence: med (impact depends on upstream caller trust).

10. severity=medium category=bug file=packages/inventory/database/migrations/2000_09_01_000016_create_inventory_reservations_table.php:27 title=Reservation identity unique() is ineffective for global (NULL-owner) rows. Description: unique(['reference','owner_type','owner_id']) does not dedupe NULLs in PG/MySQL, so concurrent global reserves with the same reference can both insert; CheckoutReservationService::reserve() relies on createOrFirst (line 42) for idempotency. Evidence: `$table->unique(['reference','owner_type','owner_id'], ...)` + `InventoryReservation::query()->createOrFirst([...])`. Recommendation: partial unique index on reference WHERE owner_type IS NULL (PG) / generated non-null owner key, or serialize on advisory lock. Confidence: high.

11. severity=medium category=bug file=packages/inventory/database/migrations/2000_09_01_000016_create_inventory_reservations_table.php:21 title=Reservations owner columns use nullableMorphs (bigint) while every other inventory table uses nullableUuidMorphs. Description: owner_id type mismatches UUID owners; joins/scopes on owner_id can fail or miscompare for UUID-PK owners. Evidence: `$table->nullableMorphs('owner');` vs levels/locations/movements `nullableUuidMorphs('owner')`. Recommendation: change to nullableUuidMorphs for consistency. Confidence: high on inconsistency, med on runtime impact.

12. severity=high category=bug file=packages/vouchers/database/migrations/2001_04_01_000003_create_voucher_wallets_table.php:40 title=Partial unique index created unconditionally — migration breaks on MySQL. Description: `CREATE UNIQUE INDEX ... WHERE redeemed_at IS NULL` runs via raw DB::statement on all drivers; MySQL has no partial-index support (unlike the GIN indexes in the other two voucher migrations, which are correctly pgsql-guarded). Evidence: `DB::statement("CREATE UNIQUE INDEX voucher_wallets_one_active_per_holder ON {$tableName} (voucher_id, holder_type, holder_id) WHERE redeemed_at IS NULL");`. Recommendation: guard by driver (pgsql/sqlite) and enforce the rule application-side or via unique key on MySQL. Confidence: high.

13. severity=high category=bug file=packages/vouchers/src/Listeners/ValidateVoucherOnCheckout.php:60 title=Checkout listener strips invalid codes from metadata but leaves discount conditions applied. Description: on invalid vouchers it rewrites VOUCHER_CODES metadata yet never removes the registered dynamic CartConditions, so the stale discount can still price into totals (unless checkout re-derives solely from metadata). Evidence: `$cart->setMetadata(...VOUCHER_CODES, $validCodes); if (config block_on_invalid) throw ...` with no condition removal. Recommendation: also remove the corresponding voucher conditions (or re-validate at totals time) and add a regression test. Confidence: med.

14. severity=medium category=bug file=packages/inventory/src/Services/InventoryService.php:377 title=getOrCreateLevel race surfaces raw unique-violation 500. Description: firstOrCreate on (inventoryable_type,id,location_id) has a DB unique key (migration levels:38) but concurrent receive/adjust callers can both pass the SELECT and one gets QueryException. Evidence: `return InventoryLevel::firstOrCreate([...], ['quantity_on_hand'=>0,...]);` with no retry. Recommendation: catch 23000/23505 and re-select (same pattern CreateVoucher uses). Confidence: med.

15. severity=medium category=bug file=packages/inventory/src/Models/InventoryLocation.php:460 title=Root path falls back to literal 'temp' and descendant rebuild is unbounded recursion. Description: updatePathAndDepth sets path='temp' when id is null (creating-order dependent); rebuildDescendantPaths() recurses with children()->get()+saveQuietly per node — N+1 writes, no depth/cycle guard (a parent cycle = infinite loop). Evidence: `$this->path = $this->id ?? 'temp';` and `foreach ($this->children()->get() as $child) { ... $child->saveQuietly(); $child->rebuildDescendantPaths(); }`. Recommendation: generate path in created/after-id-available hook, add cycle + depth guard, batch updates. Confidence: med. (Also performance.)

16. severity=medium category=bug file=packages/vouchers/src/Services/VoucherService.php:274 title=Voucher session-reservation registry has read-modify-write races. Description: reserve()/release() do Cache::get, mutate the id array in PHP, Cache::put — concurrent sessions can lose each other's entries; release-all iterates a possibly stale list. Evidence: `$sessionIds = Cache::get($sessionsKey, []); ... $sessionIds[] = $sessionId; Cache::put($sessionsKey, ...)`. Recommendation: per-session keys only (drop shared index) or Cache::lock around mutation. Confidence: med-high.

17. severity=medium category=performance file=packages/inventory/src/Listeners/DeductInventoryFromOrder.php:156 title=Direct deduction loops order items with per-item queries (N+1, unbounded). Description: deductDirectly() iterates $order->items, each item does findDeductionLocation queries + ship() transaction; no chunking/eager-load cap. Evidence: `foreach ($order->items as $item) { ... $this->deductForItem($purchasable, $item->quantity, $order); }`. Recommendation: eager-load items+purchasable, batch levels by (type,id), keep idempotency op. Confidence: high.

18. severity=medium category=performance file=packages/filament-engagement/src/Resources/FollowResource.php:119 (same BookmarkResource.php:113) title=Bulk actions process unbounded selections row-by-row. Description: mute/unfollow bulk loops every selected record with a scoped re-resolve + manager call each — N+1 queries, no chunking or queue. Evidence: `->action(function ($records): void { foreach ($records as $record) { ... ActionRecordResolver::resolve($record); app(EngagementManager::class)->muteFollow(...); } })`. Recommendation: chunk, bulk-update where manager allows, or dispatch jobs. Confidence: high.

19. severity=medium category=bug file=packages/filament-promotions/src/Resources/PromotionResource/Schemas/PromotionForm.php:39 title=Promotion form validation gaps: global code uniqueness, negative values, undeactivatable. Description: code unique(ignoreRecord) is global (should be per-owner when owner enabled — blocks legit reuse + leaks existence); discount_value/usage_limit/per_customer_limit numeric with no minValue (negatives); is_active disabledOn('edit') with no toggle action means records can't be deactivated from UI. Evidence: `TextInput::make('code')->...->unique(ignoreRecord: true)`, `TextInput::make('discount_value')->numeric()` (no min), `Toggle::make('is_active')->disabledOn('edit')`. Recommendation: owner-scoped unique rule, minValue(0), enable active toggle or add actions. Confidence: med-high.

20. severity=medium category=performance file=packages/inventory/src/Services/InventoryService.php:299 title=getTotalAvailable/getAvailability hydrate all levels in PHP instead of aggregating. Description: ->get()->sum(fn=>available) loads every level row per call (sibling getTotalOnHand correctly uses ->sum()). Hot path: allocation hasAvailableInventory/validateAvailability call it per item. Evidence: `->get()->sum(fn (InventoryLevel $level): int => $level->available);`. Recommendation: SUM(quantity_on_hand-quantity_reserved) in SQL. Confidence: high.

21. severity=low category=security file=packages/inventory/src/Services/Serial/SerialLookupService.php:57 (also :390) title=LIKE patterns interpolate unescaped user input (wildcard injection). Description: "%{$partial}%" passed as binding (no SQLi — bindings used) but %/_ in input act as wildcards, letting a search for % dump up to $limit rows. Evidence: `->where('serial_number', $likeOperator, "%{$partialSerialNumber}%")`. Recommendation: escape %/_/\ with ESCAPE clause. Confidence: high.

22. severity=low category=security file=packages/inventory/src/Models/InventoryAllocation.php:221 title=Allocation guard looks up level/batch without owner scope (defense-in-depth gap). Description: saving hook scopes the location but fetches level/batch via bare ::query()->whereKey(); only location_id equality is checked afterwards. Currently constrained by the location match, but a level/batch row at that location owned differently would not be rejected explicitly. Evidence: `$level = InventoryLevel::query()->whereKey(...)->first(); ... $batch = InventoryBatch::query()->whereKey(...)->first();`. Recommendation: wrap both in InventoryOwnerScope::applyToLocationQuery. Confidence: med. (Same note: Serial.php:429, CostLayer.php:99 batch lookups.)

23. severity=low category=security file=packages/filament-engagement/src/Resources/FollowResource.php:77 (same BookmarkResource.php:75) title=Filter dropdown options query distinct types without owner scope. Description: follower/followable/bookmarker type options use bare Model::query()->distinct()->pluck() — cross-owner type-name enumeration (low sensitivity: class names only). Evidence: `->options(fn (): array => Follow::query()->select('follower_type')->distinct()...->all())`. Recommendation: wrap in OwnerUiScope::apply. Confidence: high.

24. severity=low category=security file=packages/filament-promotions/src/Actions/IssuePromotionVouchersAction.php:71 title=Failure notifications expose raw exception messages. Description: catch(Throwable) surfaces $throwable->getMessage() (may include SQL/state details) in admin UI; same in FromListAction:71. Evidence: `->body($throwable->getMessage())->danger()->send();` (report() already logs). Recommendation: generic user message, keep report($throwable). Confidence: high on behavior, low on impact (admin-only).

25. severity=low category=bug file=packages/vouchers/src/Services/VoucherService.php:366 title=redeem() builds Money via dynamic currency method without validation. Description: Money::{$currency}(...) where $currency derives from voucher->currency/config; unknown code throws at runtime. Evidence: `$money = Money::{$currency}(max(0, $discount));`. Recommendation: validate/whitelist currency, fallback to default. Confidence: med.

26. severity=low category=bug file=packages/filament-engagement/src/Actions/FollowAction.php:20 (same Bookmark/Subscribe/React/Respond/SetReminder actions) title=Actions pass auth()->user() unchecked; guest hits TypeError. Description: manager calls assume non-null user; Filament normally authenticates, but no guard if reused on public tables. Evidence: `app(EngagementManager::class)->follow(auth()->user(), $record);`. Recommendation: abort/throw auth exception when user is null. Confidence: med.

27. severity=low category=performance file=packages/vouchers/src/Traits/HasVouchers.php:90 title=Wallet helpers load all rows then filter in PHP, unbounded. Description: getAvailableVouchers()/getExpiredVouchers() ->get() every wallet entry (+voucher) and filter canBeUsed()/isExpired() in memory. Evidence: `->whereNotNull('claimed_at')->whereNull('redeemed_at')->get(); return $wallets->filter(...)`. Recommendation: push predicates to SQL (join/expiry/status), paginate. Confidence: high.

28. severity=low category=bug file=packages/inventory/src/Services/Stock/InventoryAllocationService.php:74 title=allocate() never validates ttlMinutes (negative/zero yields instantly-expired rows). Description: quantity>0 checked but $ttlMinutes flows straight to now()->addMinutes(). Evidence: `CarbonImmutable::now()->addMinutes($ttlMinutes)` with no guard (CheckoutReservationService clamps, direct callers don't). Recommendation: max(1,$ttlMinutes) or InvalidArgumentException. Confidence: high.

29. severity=low category=security file=packages/inventory/src/Exports/ExportService.php:42 title=toCsvFile concatenates caller path without traversal check. Description: $path.'/'.$filename.'.csv' + fopen('w') allows directory traversal if $path ever carries user input; no in-package caller found (accepted string param), so currently latent. Evidence: `$fullPath = $path . '/' . $export->getFilename() . '.csv'; $output = fopen($fullPath, 'w');`. Recommendation: realpath-constrain to an export disk, reject .. / absolute paths. Confidence: low (no user-reachable path found).

POSITIVES (brief): owner scoping is strong in most places — InventoryOwnerScope write guards on Level/Location/Allocation/Movement/Batch + movement cross-owner rejection; Voucher queries via QueriesVouchers (owner+withCount, no N+1); VoucherLookupCache owner-keyed, skips caching when includeGlobal, invalidated on save/delete/code-change; RecordVoucherUsage locks voucher row + idempotency keys + limit checks in-transaction; ExpireVouchersCommand chunkById with per-owner context; filament-engagement OwnerUiScope on all resource queries + ActionRecordResolver revalidation; filament-promotions OwnerWriteGuard on delete paths + permission gates; JsonDisplay escapes before ->html() (no XSS); NearestLocationStrategy binds CASE params; exports use cursor() streaming; migrations use uuid PKs, no FK constraints/cascades (manual deletes in Voucher::deleting, Level/Location deleting), money in int minor units/basis points; no SoftDeletes/$guarded/forceFill/eval/unserialize/shell found; voucher targeting fails closed on degenerate definitions.

OUT OF SCOPE / NOT FOUND: no routes, HTTP controllers, or queued jobs in these 4 packages; no tests/ dirs; SSRF/path-traversal/deserialization/XSS-injection — no reachable vectors found (raw-SQL hits are all static fragments or bound params; table-name interpolation comes from config only).

### Prior-audit chunk: filament-promotions

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-promotions` LOW — scoped + `withCount(usages)` GOOD; uncached badge.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-promotions

- [R2:#4] DOWNGRADED (was high) low sec | packages/filament-promotions/src/Actions/IssuePromotionVouchersAction.php:55 | Filament maxValue(100) validates server-side; handler lacks own clamp; admin-only
- [R2:#5] DOWNGRADED (was high) medium perf | packages/filament-promotions/src/Actions/IssuePromotionVouchersFromListAction.php:91 | Promotion OwnerScope global scopes it; unbounded get() remains; DUP AUD:B5
- [R2:#6] DOWNGRADED (was high) medium perf | packages/promotions/src/Support/PromotionPerformanceInsights.php:203 | Promotion+Order OwnerScope globals scope reads; O(orders) full-table get+PHP parse per render
- [R2:#19] CONFIRMED medium bug | packages/filament-promotions/src/Resources/PromotionResource/Schemas/PromotionForm.php:39 | global unique (DB unique global too) + no minValue; deactivatable via EditPromotion:61
- [R2:#24] CONFIRMED low sec | packages/filament-promotions/src/Actions/IssuePromotionVouchersAction.php:71 | catch() surfaces getMessage() in admin notification; same in FromListAction:76
- [AUD:B1] ADOPTED low perf | packages/filament-promotions/src/Resources/PromotionResource.php:113 | scoped query ok; badge is uncached count()
- [AUD:B2] ADOPTED low bug | packages/filament-promotions/src/Resources/PromotionResource.php:138 | G1 PASS: nav group via config, no static group
- [AUD:B3] ADOPTED medium perf | packages/filament-promotions/src/Resources/PromotionResource.php:113 | G2: uncached getNavigationBadge count per render
- [AUD:B4] ADOPTED low perf | packages/filament-promotions/src/Resources/PromotionResource/Tables/PromotionsTable.php:33 | G3: all columns scalar; no in-pkg instance
- [AUD:B5] ADOPTED medium perf | packages/filament-promotions/src/Actions/IssuePromotionVouchersFromListAction.php:91 | G4: DUP R2:#5; table Select is enum-only
- [AUD:B6] ADOPTED low bug | packages/filament-promotions/src/Resources/PromotionResource.php:1 | G5: cited files in filament-vouchers/affiliates; none here
- [AUD:B7] ADOPTED low perf | packages/filament-promotions/src/Widgets/PromotionStatsWidget.php:18 | G6: no collection sums in pkg; widget cost is core full-table get

### Prior-audit chunk: inventory

### Prior-audit section
### inventory
Bugs:
- `InventoryReservation:50-59`, `InventoryOperation:46-53` HIGH — `owner_*/order_id/status` fillable, no `booted` guard (unlike Level).
- `InventoryLevel:81-99` HIGH — `quantity_on_hand/reserved/available` fillable; `saving` validates only owner/location, bypasses ledger.
- `InventoryLocation:393-469` MEDIUM (was HIGH) — levels/allocations/children cascaded; movements/batches/serials NOT cascaded.
Security: unvalidated inbound IDs MEDIUM-HIGH — `Reservation/Operation order_id`, `Level preferred_supplier_id`, `Movement user_id` (Level validates location owner only). Reports downgraded to MEDIUM-verify — `StockLevelReport/MovementAnalysisReport/InventoryService` consistently scope; need line-level proof for single `DB::query()` totals, not blanket HIGH.
Performance:
- Allocation loop O(n) writes MEDIUM — per-allocation `decrement` + `Movement::create` in txn.
- Reports `get()` no pagination HIGH — `StockLevelReport`, `MovementAnalysisReport`, `InventoryKpiService:256` load all rows → OOM. Exports are paginated (`StockLevelExport:72`, `BatchExport:60`, `MovementExport:79`, `ValuationExport:57` all `cursor()`) — split from original blanket claim.

### Prior-audit fix-first rows
| 26 | inventory | `Models/InventoryLevel.php:81-99` | `quantity_on_hand/reserved/available` fillable — bypasses ledger | HIGH |
| — | inventory | `InventoryReservation:50-59`, `InventoryOperation:46-53` | `owner_*/order_id/status` fillable, no `booted` guard | HIGH |

#### Verdicts: inventory

- [R2:#7] FALSE high sec | packages/inventory/src/Models/InventoryLocation.php:133 | OwnerScope filters lookup; HasOwnerScopeKey makes (owner_scope,code) per-owner; DEFAULT correct
- [R2:#8] FALSE high sec | packages/inventory/src/Models/InventoryReorderSuggestion.php:136 | Location find carries OwnerScope global; other-owner id returns null and throws
- [R2:#9] CONFIRMED medium sec | packages/inventory/src/Services/Stock/CheckoutReservationService.php:320 | any Model subclass resolvable from cart-snapshot attrs; level rows creatable
- [R2:#10] CONFIRMED medium bug | packages/inventory/database/migrations/2000_09_01_000016_create_inventory_reservations_table.php:27 | NULL owners defeat unique on PG/MySQL; txn cannot dedupe; FALSE-list entry differs
- [R2:#11] CONFIRMED medium bug | packages/inventory/database/migrations/2000_09_01_000016_create_inventory_reservations_table.php:21 | nullableMorphs vs levels:33 nullableUuidMorphs; breaks UUID-PK owners
- [R2:#14] CONFIRMED medium bug | packages/inventory/src/Services/InventoryService.php:377 | firstOrCreate plus DB unique with no 23000 retry; concurrent callers 500
- [R2:#15] CONFIRMED medium bug | packages/inventory/src/Models/InventoryLocation.php:460 | 'temp' dead (HasUuids first); parent cycle gives infinite recursion; N+1 saves
- [R2:#17] CONFIRMED medium perf | packages/inventory/src/Listeners/DeductInventoryFromOrder.php:156 | no eager load; per-item purchasable, location search, ship txn
- [R2:#20] CONFIRMED medium perf | packages/inventory/src/Services/InventoryService.php:299 | get()->sum(available) per call; getTotalOnHand already SQL
- [R2:#21] CONFIRMED low sec | packages/inventory/src/Services/Serial/SerialLookupService.php:57 | unescaped %/_ in LIKE at :57 and :390; bound yet wildcard-dumpable to limit
- [R2:#22] FALSE low sec | packages/inventory/src/Models/InventoryAllocation.php:221 | Level/Batch/Serial/CostLayer all OwnerScoped; location and owner-tuple match enforced
- [R2:#28] CONFIRMED low bug | packages/inventory/src/Services/Stock/InventoryAllocationService.php:74 | ttlMinutes unvalidated into addMinutes; only checkout path clamps
- [R2:#29] CONFIRMED low sec | packages/inventory/src/Exports/ExportService.php:42 | path concat without traversal check; latent, zero in-package callers
- [AUD:B1] ADOPTED high bug | packages/inventory/src/Models/InventoryReservation.php:50 | owner/order/status fillable, no custom booted; DUP AUD:Q#UN
- [AUD:B2] ADOPTED high bug | packages/inventory/src/Models/InventoryLevel.php:81 | quantity columns fillable, bypass ledger; DUP AUD:Q#26
- [AUD:B3] ADOPTED medium bug | packages/inventory/src/Models/InventoryLocation.php:445 | deleting cascades levels/allocs only; movements/batches/serials orphaned
- [AUD:B4] ADOPTED medium sec | packages/inventory/src/Models/InventoryLevel.php:96 | inbound order/supplier/user ids unvalidated; reports scoped, needs line proof
- [AUD:B5] ADOPTED medium perf | packages/inventory/src/Services/Stock/InventoryAllocationService.php:97 | per-level loop of alloc+decrement+movement writes inside txn
- [AUD:B6] ADOPTED high perf | packages/inventory/src/Reports/StockLevelReport.php:140 | unbounded get() incl KpiService:256, MovementAnalysis; exports cursor-split ok
- [AUD:Q#26] ADOPTED high bug | packages/inventory/src/Models/InventoryLevel.php:81 | DUP AUD:B2, counted once
- [AUD:Q#UN] ADOPTED high bug | packages/inventory/src/Models/InventoryReservation.php:50 | DUP AUD:B1 (unnumbered row), counted once

### Prior-audit chunk: vouchers

### Prior-audit section
### vouchers
Bugs:
- `VoucherWallet:81-101` HIGH — `claim/markAsRedeemed` check-then-set, no txn/lock/`whereNull` → double redeem.
- `VoucherService:361-366` MEDIUM — percentage redeem records 0-value usage consuming `usage_limit`.
- `RecordVoucherUsage:78-90` MEDIUM — currency never validated vs voucher currency.
- `reserve()/release():257-338` MEDIUM — advisory cache dead code (zero readers).
- Idempotency gap MEDIUM — null key when neither `metadata.idempotency_key` nor `order_id` → plain `create()`, NULLs don't dedup.
- `VoucherValidator:153-171` MEDIUM — unknown cart shape totals 0 (fragile fail-closed).
- `AddVoucherToWallet` CORRECTED — unique `voucher_wallets_one_active_per_holder WHERE redeemed_at IS NULL` EXISTS; residual is concurrent 500s (no lock/23000 rescue), not silent duplicates. `VoucherService:216-232 addToWallet` skips even the `first` check.
Security:
- `UpdateVoucher:25-27` CRITICAL — `VoucherModel::where(code)->firstOrFail()` unscoped; `Voucher booted:596-638` has zero owner checks.
- `resolveRedeemedByOrder:405-407` MEDIUM — `Order::select()->find()` no `forOwner`.
- `removeFromWallet:244-248` MEDIUM — `VoucherWallet::where(voucher,holder)->delete()` no owner predicate.
- Per-user limit unscoped + guests skipped MEDIUM — `RecordVoucherUsage:67-76` counts with no owner scope; `Validator:88-102` only `Auth::user()`.
- `ExpireVouchersCommand withoutOwnerScope` LOW — needs explicit global context.
Performance:
- `getTimesUsedAttribute:393-406` N+1 MEDIUM — `usages()->count()` per voucher unless `withCount`.
- `scopeLive:254-274` correlated subquery per row MEDIUM — prefer `withCount+having`/join.
- `getUsageHistory:204-206` unbounded MEDIUM.
- `include_global=true` bypasses `VoucherLookupCache:36-38` LOW.
Good: `RecordVoucherUsage:43-50` txn+lock+recheck+unique; `ExpireVoucher` locks; targeting fails closed.

### Prior-audit fix-first rows
| 1 | vouchers | `Actions/UpdateVoucher.php:25-27` | Unscoped `where(code)` write — any tenant can rewrite another's voucher | CRITICAL |
| 29 | vouchers | `Models/VoucherWallet.php:81-101` | `claim()` check-then-set race → double redeem | HIGH |

#### Verdicts: vouchers

- [R2:#1] FALSE high sec | packages/vouchers/src/Actions/UpdateVoucher.php:25 | DUP AUD:B8,Q#1; OwnerScope global filters query; cf FALSE-list events entry
- [R2:#2] DOWNGRADED (was high) medium sec | packages/vouchers/src/Actions/UpdateVoucher.php:38 | owner-hijack throws via guardOwnedOwnerWrite; applied_count/code/timestamps still mass-assignable
- [R2:#3] CONFIRMED high bug | packages/vouchers/src/Actions/CreateVoucher.php:47 | direct $data[type]/[value], zero validation; fromArray exists but unused, also lacks ranges
- [R2:#12] CONFIRMED high bug | packages/vouchers/database/migrations/2001_04_01_000003_create_voucher_wallets_table.php:40 | partial unique unguarded; sibling GINs pgsql-guarded; breaks MySQL migrate
- [R2:#13] DOWNGRADED (was high) medium bug | packages/vouchers/src/Listeners/ValidateVoucherOnCheckout.php:60 | strips metadata only; setMetadata marks nothing dirty; same-request totals keep applied condition
- [R2:#16] DOWNGRADED (was medium) low bug | packages/vouchers/src/Services/VoucherService.php:274 | RMW race real but zero cache readers; DUP AUD:B4 advisory dead code
- [R2:#25] CONFIRMED low bug | packages/vouchers/src/Services/VoucherService.php:366 | Money::{$currency} unvalidated; corrupt currency (see R2:#3) throws at runtime
- [R2:#27] CONFIRMED low perf | packages/vouchers/src/Traits/HasVouchers.php:90 | getAvailable/getExpired ->get() all wallets then filter in PHP, unbounded
- [AUD:B1] ADOPTED high bug | packages/vouchers/src/Models/VoucherWallet.php:81 | claim/markAsRedeemed check-then-set, no txn/lock; DUP AUD:Q#29
- [AUD:B2] ADOPTED medium bug | packages/vouchers/src/Services/VoucherService.php:361 | percentage redeem records 0-value usage consuming usage_limit
- [AUD:B3] ADOPTED medium bug | packages/vouchers/src/Actions/RecordVoucherUsage.php:78 | usage currency never compared to voucher currency
- [AUD:B4] ADOPTED medium bug | packages/vouchers/src/Services/VoucherService.php:257 | reservation cache has no readers outside reserve/release; DUP R2:#16
- [AUD:B5] ADOPTED medium bug | packages/vouchers/src/Actions/RecordVoucherUsage.php:92 | null idempotency key falls to plain create(), no dedup
- [AUD:B6] ADOPTED medium bug | packages/vouchers/src/Services/VoucherValidator.php:153 | unknown cart shape totals 0; fragile fail-closed
- [AUD:B7] ADOPTED medium bug | packages/vouchers/src/Services/VoucherService.php:216 | addToWallet skips first-check/lock; concurrent dup hits partial unique 500
- [AUD:B8] FALSE critical sec | packages/vouchers/src/Actions/UpdateVoucher.php:25 | DUP R2:#1,AUD:Q#1; OwnerScope global covers lookup, guards cover writes
- [AUD:B9] DOWNGRADED (was medium) low sec | packages/vouchers/src/Services/VoucherService.php:405 | Order has OwnerScope global; residual is UUID-guess read-only meta
- [AUD:B10] FALSE medium sec | packages/vouchers/src/Services/VoucherService.php:244 | VoucherWallet has OwnerScope global; delete query scoped when enabled
- [AUD:B11] ADOPTED medium sec | packages/vouchers/src/Actions/RecordVoucherUsage.php:67 | counts scoped via voucher; guests skipped, validator Auth-user only
- [AUD:B12] ADOPTED low sec | packages/vouchers/src/Console/Commands/ExpireVouchersCommand.php:30 | withoutOwnerScope enumeration intentional; writes per-owner via withOwner
- [AUD:B13] ADOPTED medium perf | packages/vouchers/src/Models/Voucher.php:393 | usages()->count() fallback per row; mitigated by withCount in voucherQuery
- [AUD:B14] ADOPTED medium perf | packages/vouchers/src/Models/Voucher.php:254 | scopeLive correlated count subquery per row
- [AUD:B15] ADOPTED medium perf | packages/vouchers/src/Services/VoucherService.php:193 | getUsageHistory unbounded ->get()
- [AUD:B16] ADOPTED low perf | packages/vouchers/src/Support/VoucherLookupCache.php:36 | include_global=true bypasses cache by design
- [AUD:Q#1] FALSE critical sec | packages/vouchers/src/Actions/UpdateVoucher.php:25 | DUP R2:#1+AUD:B8, counted once
- [AUD:Q#29] ADOPTED high bug | packages/vouchers/src/Models/VoucherWallet.php:81 | DUP AUD:B1, counted once

### Prior-audit chunk: filament-engagement

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-engagement` GOOD — all 7 `OwnerUiScope+with()`.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-engagement

- [R2:#18] CONFIRMED medium perf | packages/filament-engagement/src/Resources/FollowResource.php:119 | bulk actions loop all records with re-resolve plus manager call; same Bookmark:113
- [R2:#23] FALSE low sec | packages/filament-engagement/src/Resources/FollowResource.php:77 | Follow/Bookmark OwnerScope globals scope options; cf FALSE-list events entry
- [R2:#26] CONFIRMED low bug | packages/filament-engagement/src/Actions/FollowAction.php:20 | auth()->user() unchecked in 8 actions; guest reuse hits TypeError
- [AUD:B1] ADOPTED low bug | packages/filament-engagement/src/Resources/FollowResource.php:46 | GOOD: OwnerUiScope applied; scalar columns so no with() needed
- [AUD:B2] ADOPTED low bug | packages/filament-engagement/src/Resources/FollowResource.php:36 | G1 PASS: nav group via config, no static group
- [AUD:B3] ADOPTED low perf | packages/filament-engagement/src/Resources/FollowResource.php:1 | G2: no getNavigationBadge override; no in-pkg instance
- [AUD:B4] ADOPTED low perf | packages/filament-engagement/src/Resources/FollowResource.php:55 | G3: all columns scalar; no in-pkg instance
- [AUD:B5] ADOPTED low perf | packages/filament-engagement/src/Resources/BookmarkCollectionResource.php:54 | G4: Selects are enum/static options only
- [AUD:B6] ADOPTED low bug | packages/filament-engagement/src/Resources/FollowResource.php:1 | G5: cited files elsewhere; no in-pkg instance
- [AUD:B7] ADOPTED low perf | packages/filament-engagement/src/Resources/FollowResource.php:1 | G6: cited files elsewhere; no in-pkg instance

---

## Round 2 — `filament-signals`, `promotions`, `filament-vouchers`, `filament-ticketing`

### E2E findings (verbatim)

End-to-end review: filament-signals, promotions, filament-vouchers, filament-ticketing. No tests or routes ship in any of the four packages (file enumeration over 136 files; testing gap).

== PROMOTIONS ==
P1 [high/bug] packages/promotions/src/Services/PromotionService.php:209-274 per-customer limit fail-open. withinCustomerLimit() returns true when customer id is missing, orders package absent, or ANY Throwable occurs ("limit skipped" debug logs). A transient DB error or guest checkout silently disables per_customer_limit. Evidence: `catch (Throwable $exception) { ... return true; }` L266-273; L217-223; L228-234. Recommend: fail closed when a limit is set (deny or count as exhausted), or explicit config `per_customer_limit_fail_open`, and rate-limit the log. Confidence: high.
P2 [high/performance] same file L175-263 O(PxO) evaluation. applicablePromotions() chunkById over ALL automatic promos, and matchesContextAt() calls withinCustomerLimit() per promotion, which re-queries + chunks the customer's full order history per promotion. Evidence: L198-206 loop; L237-263 per-promo order scan. Recommend: load the customer's promotion-usage counts once per request (one order query, parse allocations once, map promo_id=>count) and reuse. Confidence: high.
P3 [high/security+performance] packages/promotions/src/Support/PromotionPerformanceInsights.php:114-123,203-206 unscoped unbounded analytics. promotions() has no forOwner; Order::query()->count()/->get() unscoped and unbounded (loads every order + metadata into PHP). Cross-owner leak + OOM. Evidence: `private function promotions()` returns raw query; `$orders = Order::query()->select(...)->get()`. Recommend: apply forOwner/OwnerQuery, chunk or push aggregates to SQL, permission-gate. Confidence: high.
P4 [medium/bug+security] packages/promotions/src/Console/Commands/DeactivateExpiredPromotionsCommand.php:23-42 unscoped, unbounded, owner-guard break. No forOwner; ->get() all expired; each DeactivatePromotion->handle() update trips Promotion::updating owner guard (Models/Promotion.php:459-469) which throws AuthorizationException for owned promos when console has no OwnerContext. Also N+1 (update+fresh+event each). Recommend: chunkById, run under explicit per-owner/system context, consider bulk update + events. Confidence: high.
P5 [medium/bug] packages/promotions/src/Actions/CreatePromotion.php:13-15 no validation. Raw $data to create; model saving() only normalizes code + validates conditions. No checks: type/discount_value range (percentage 500 or negative), usage_limit/per_customer/min amounts >=0, ends_at>starts_at. Evidence: saving hook L471-496 has no numeric checks. Recommend: action-level validator (percentage 0-100, non-negative money, date order). Confidence: high.
P6 [medium/bug] packages/promotions/src/Actions/DeactivatePromotion.php:12-21 fresh() null + missing deactivated_at. fresh() can return null -> dispatch(null) TypeError; never sets deactivated_at although the column exists. Recommend: null-check, set deactivated_at=now. Confidence: high.
P7 [medium/security] packages/promotions/database/migrations/2000_12_01_000001_create_promotions_table.php:19 global unique code. With owner enabled, one tenant can squat codes and probe others via unique violations. Recommend: replace with owner-scoped composite unique + scoped validation. Confidence: high.
P8 [low/bug] packages/promotions/src/Models/Promotion.php:363-369 percentage discount uncapped. Fixed clamps via min(); percentage `round(price*value/100)` exceeds price when value>100. Recommend: min() both branches. Confidence: high.
P9 [low/performance] same file L60,192-214 Octane-unsafe static. $issuedVoucherTrackingSupported caches Schema check process-wide; stale after migrations, shared across tenants. Recommend: request-scoped cache + reset hook. Confidence: med.
P10 [low/performance] migration L48-53 promotionables PK (promotion_id,...) serves promo-side only; product/category-side lookups need index on (promotionable_type,promotionable_id). Confidence: med.

== FILAMENT-VOUCHERS ==
V1 [high/security] packages/filament-vouchers/resources/views/widgets/voucher-suggestions.blade.php:74 stored XSS via voucher code. `wire:click="applySuggestion('{{ $voucher->code }}')"` interpolates code into a single-quoted action; codes allow arbitrary chars (VoucherForm code L50-60 has no alpha-dash; BulkGenerate prefix L48-52 maxLength only). A quote in a code breaks out (HTML-escaped entity is decoded before Livewire parses). Recommend: pass record key and resolve server-side; add alpha_dash validation on code/prefix. Confidence: high.
V2 [high/bug] packages/filament-vouchers/src/Widgets/RedemptionTrendChart.php:41,126-133 tamperable filter DoS. Public Livewire $filter cast to int with no allowlist; loop builds one collection row per day. filter=9999999 => multi-million-iteration render. Recommend: validate against [7,14,30,90], clamp, default 30. Confidence: high.
V3 [medium/security] packages/filament-vouchers/src/Actions/ManualRedeemVoucherAction.php:31-73 missing server revalidation. visible() checks allows_manual_redemption + limit but action() never rechecks; discount_amount has no min (negative/zero); invalid input becomes 0 via MoneyHelper; no permission check. Recommend: recheck eligibility in action, minValue(0.01), reject unparseable amounts, authorize. Confidence: high.
V4 [medium/bug] packages/filament-vouchers/src/Support/MoneyHelper.php:116-125 silent zero. decimalToInteger returns 0 on regex mismatch, so garbage money input becomes 0 across forms. Recommend: return null/throw + validation error. Confidence: high.
V5 [medium/bug] packages/filament-vouchers/src/Actions/BulkGenerateVouchersAction.php:90-111 partial batch + TypeError + collisions. 100 sequential creates, no transaction; nullable prefix -> mb_strtoupper(null) TypeError; Str::random(6) with no unique retry aborts mid-batch leaving partial rows. Recommend: transaction, `$data['prefix'] ?? ''`, retry-on-duplicate code generation, server-side count clamp. Confidence: high.
V6 [medium/security] packages/filament-vouchers/src/Resources/VoucherResource/Pages/CreateVoucher.php:37-56 silent global creation. Owner enabled + no resolved owner => sets null/null instead of throwing (promotions throws NoCurrentOwnerException); form Ownership input is always overwritten by context, misleading admins. Recommend: require explicit global context; hide owner fields or honor them for authorized users. Confidence: high.
V7 [medium/performance] packages/filament-vouchers/src/Widgets/VoucherSuggestionsWidget.php:79-118 unbounded + N+1. ->get() all live vouchers of a currency; CartInstanceManager::resolve + getAppliedVouchers executed per voucher inside filter. Recommend: limit/order, resolve cart once, fetch applied codes once. Confidence: high.
V8 [medium/performance] packages/filament-vouchers/src/Widgets/VoucherUsageTimelineWidget.php:74-78,122-124 double full scan. Timeline and summary each ->get() every usage for the voucher; sum/unique computed in PHP. Recommend: paginate (limit 50) + SQL aggregates for summary, single query. Confidence: high.
V9 [medium/performance] packages/filament-vouchers/src/Support/VoucherStatsAggregator.php:28-40 + Widgets/VoucherWalletStatsWidget.php:25-41,94-98 query fan-out. 6 uncached overview queries per render; wallet stats 6 counts + 7 daily whereDate counts. Recommend: short-TTL owner-keyed cache; single GROUP BY for trend. Confidence: high.
V10 [low/security+performance] packages/filament-vouchers/src/Exports/VoucherUsageExporter.php:22-84 per-row resolve fan-out. resolve()/orderId()/orderNumber() called ~5x per row (N+1); modifyQuery adds no explicit owner scope (inherits table query - verify in integration). Recommend: memoize one resolve per record; confirm export path uses scoped table query. Confidence: med.
V11 [low/bug] packages/filament-vouchers/src/Resources/VoucherResource/Pages/EditVoucher.php:54-77 hydrate crash. ConditionTarget::from($definition) unguarded; legacy/invalid stored definition breaks the edit page. Recommend: try/catch with default-preset fallback. Confidence: med.
V12 [low/bug] packages/filament-vouchers/src/Resources/VoucherResource/Schemas/VoucherForm.php:54,85-106,375 global unique code + uncapped percentage + truncation. Same cross-owner code issue as P7; percentage value no max; upline `(int)$state` truncates decimals. Recommend: scoped unique, max 100 for percent, decimal-safe parse. Confidence: med.
V13 [low/performance] navigation badges count on every render (Resources/VoucherResource.php:114-119; VoucherWalletResource.php:44-51), uncached. Recommend: cache or drop badges. Confidence: high.

== FILAMENT-SIGNALS ==
S1 [medium/security] packages/filament-signals/src/Resources/SignalInteractionRuleResource/Pages/ListSignalInteractionRules.php:47-152,153-359,360-458 scanner actions lack create authorization. scanPage/rescanRoute/createFromPreview have no ->authorize()/policy gate; any list viewer can create up to 200 rules (max_candidates L216-222). Recommend: authorize create on all three actions + visible() gating. Confidence: high.
S2 [medium/bug] packages/filament-signals/src/Pages/ReportPage.php:17-28 + Concerns/InteractsWithSignalsDateRange.php:60-62 + resources/views/pages/live-activity-report.blade.php:8 unvalidated URL dates. #[Url] dateFrom/dateTo parsed with CarbonImmutable::parse in filter action and blade; ?dateFrom=garbage => 500. No range cap => multi-year heavy reports. Recommend: validate Y-m-d, clamp range (e.g. <=366d), try/catch fallback. Confidence: high.
S3 [medium/security] CreateSignalInteractionRule.php + EditSignalInteractionRule.php (8 lines each, no mutateFormData) never revalidate tracked_property_id server-side, unlike SavedSignalReport (CreateSavedSignalReport.php:19-22 uses SavedSignalReportMutationGuard). Relies on Filament relationship validation only, against the package's own guardrail. Recommend: wire TrackedPropertyMutationGuard into both pages. Confidence: med.
S4 [medium/security] report pages lack canAccess (LiveActivityReport.php:35-38; ConversionFunnelReport.php:49-52; SignalsDashboard.php:31-34). Navigation gated by feature flag only; any panel-authed user can open URLs directly. LiveActivity exposes identity/external_id, user id/type, IP, geo (L81-143). Recommend: FilamentPermission-backed canAccess per page. Confidence: high.
S5 [low/security] Schemas/SignalInteractionRuleForm.php:31-36 global unique slug => cross-owner squat/enumeration. Recommend: owner-scoped unique. Confidence: med.
S6 [medium/performance] Support/InteractionRuleScanner.php:103-144 unbounded source walk. allFiles over resources/views + app/Livewire, whole-file reads line-by-line, no file/line cap per scan. Recommend: cap files/bytes, cache per trigger type, move to job. Confidence: high.
S7 [low/bug+performance] ListSignalInteractionRules.php:120-143,329-348,580-591 bulk create without transaction + uniqueSlug exists-loop (race => unique violation; N queries per slug). Recommend: transaction + constraint-retry slug. Confidence: med.
S8 [low/security] InteractionRuleScanner.php:176-199 discoverRoutePatterns() feeds every GET route (incl. admin) into the scan datalist. Recommend: filter to frontend routes / gate modal. Confidence: med.

== FILAMENT-TICKETING ==
T1 [medium/security] packages/filament-ticketing/src/Resources/TicketTypeResource.php:71-80 unscoped ticketable picker + fragile search. MorphToSelect has no modifyQueryUsing scoping (cross-owner attach persists; listing filter at L50-65 only hides it afterwards); searchColumns name/code/title applied to every registered type => SQL error when a type lacks one. Recommend: per-type scoped queries + per-type search columns. Confidence: med (core registry behavior unresolved).
T2 [medium/bug] same file L86-126 validation gaps: code no unique; admits_quantity no min (0/negative); min/max no cross-check; sales dates no afterOrEqual; price no min; currency free text. Recommend: scoped unique, minValue(1), lte/gte, date order, currency allowlist + uppercase. Confidence: high.
T3 [medium/bug] same file L98-103,146-147 money handling. Price is float numeric with hardcoded '$' prefix and no minor-units conversion (vouchers use MoneyHelper); currency defaults USD vs repo MYR. Rounding/truncation risk depends on core casts (unresolved, core not in scope). Recommend: confirm core casts; adopt cents + MoneyHelper pattern. Confidence: med.
T4 [medium/security] no authorization on any of the 4 resources (no canViewAny/canCreate/policies; create/edit pages registered unguarded). Any panel user can CRUD ticket types. Recommend: FilamentPermission-backed policies like signals/vouchers. Confidence: high.
T5 [low/performance] TicketTypeProductsRelationManager.php + TicketTypeComponentsRelationManager.php tables show nested product.name/componentTicketType.name with no eager loads => N+1 per row. Recommend: with() via modifyQueryUsing. Confidence: med.

== POSITIVES (brief) ==
Promotions: atomic tryIncrementUsage with usage_limit guard; owner write/delete guards in boot; listener verifies session owner-tuple match; migration uses uuid PKs, no FK (foreignUuid without constrained), no SoftDeletes. Signals: SignalsModelReferenceGuard + SavedSignalReport/TrackedProperty mutation guards + URL-state sanitizer; scan-preview cache owner+user keyed with TTL; SSRF-hardened fetch (PublicHttpUrlGuard + PinnedHttpClient); all resources forOwner-scoped; blades escaped. Vouchers: consistent OwnerQuery/OwnerWriteGuard scoping across resources/actions/widgets/bridge; ValidationException mapping for DSL; escaped blades; paginated tables. Ticketing: OwnerUiScope (+whereHasMorph on ticketable) on all resources; eager with() on pass/holder/transfer tables; config-gated resource registration; navigation group via config + getNavigationGroup everywhere.

### Prior-audit chunk: filament-signals

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-signals` GOOD — all `forOwner()->with()` + `SignalsModelReferenceGuard`.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-signals

- [R2:S1] CONFIRMED med sec | src/Resources/SignalInteractionRuleResource/Pages/ListSignalInteractionRules.php:47-458 | scanPage/rescanRoute/createFromPreview: no authorize/policy gate; list viewers can create rules.
- [R2:S2] CONFIRMED med bug | src/Pages/ReportPage.php:17-28 | #[Url] dates parsed via CarbonImmutable::parse in action+blade; garbage => 500; sanitizer skips dates.
- [R2:S3] CONFIRMED med sec | src/Resources/SignalInteractionRuleResource/Pages/CreateSignalInteractionRule.php:1-28 | No mutateFormData/guard wiring; TrackedPropertyMutationGuard exists and is used by sibling resources only.
- [R2:S4] CONFIRMED med sec | src/Pages/LiveActivityReport.php:35-38 | No canAccess on LiveActivity/ConversionFunnel/SignalsDashboard; nav flag only; identity/IP/geo exposed.
- [R2:S5] CONFIRMED low sec | src/Resources/SignalInteractionRuleResource/Schemas/SignalInteractionRuleForm.php:31-36 | Global unique slug; cross-owner squat/enumeration.
- [R2:S6] CONFIRMED med perf | src/Support/InteractionRuleScanner.php:103-144 | allFiles + whole-file line scan, no file/byte cap (maxCandidates caps results only).
- [R2:S7] CONFIRMED low bug+perf | src/Resources/SignalInteractionRuleResource/Pages/ListSignalInteractionRules.php:120-143,580-591 | Bulk create w/o txn; uniqueSlug exists-loop races + N queries.
- [R2:S8] CONFIRMED low sec | src/Support/InteractionRuleScanner.php:176-199 | discoverRoutePatterns lists every GET route incl. admin into scan datalist.
- [AUD:B1] ADOPTED info sec | src/Resources/SignalInteractionRuleResource/Pages/ListSignalInteractionRules.php:524-538 | GOOD note; forOwner + SignalsModelReferenceGuard usage confirmed.
- [AUD:B2] ADOPTED info nav | src/Pages/SignalsDashboard.php:21-34 | G1 PASS (config group/sort); light confirm only.
- [AUD:B3] ADOPTED info perf | src/Resources/:1 | G2 N/A here: no getNavigationBadge found in this package.
- [AUD:B4] ADOPTED med perf | src/Pages/LiveActivityReport.php:68-143 | G3; relation cols present, eager-load depends on service getTableQuery (not re-checked).
- [AUD:B5] ADOPTED med perf | src/Resources/SignalInteractionRuleResource/Pages/ListSignalInteractionRules.php:163-167 | G4 pattern present: options(pluck()->all()) whole-table loads.
- [AUD:B6] ADOPTED info perf | audits/.staging-verify/vpkg/audit-filament-signals.md:7 | G5 vouchers-scoped; N/A here.
- [AUD:B7] ADOPTED med perf | audits/.staging-verify/vpkg/audit-filament-signals.md:8 | G6 scoped to cashier-chip/inventory; N/A here.

### Prior-audit chunk: promotions

### Prior-audit section
### promotions
Bugs:
- `DeactivatePromotion:14` MEDIUM — never sets `deactivated_at`.
- `MarkPromotionAsUsedOnOrderPlaced:106` MEDIUM — atomic `tryIncrementUsage` but no per-order dedup; redelivery double-counts.
- `PromotionService:209-274` MEDIUM — `per_customer_limit` fails open (no customer / missing orders pkg / catch → `true`).
- Global `code` unique LOW — blocks cross-owner reuse + enumeration oracle.
- `Promotion:366` LOW — `round()` vs voucher `intdiv(+5000,10000)` 1¢ drift.
Security:
- `PromotionPerformanceInsights:114-123,203-206` HIGH — owner-blind `Promotion::query()` + unscoped `Order::select()->get()`.
- `DeactivateExpiredPromotionsCommand:23-26` HIGH — cross-tenant sweep; iterate owners explicitly.
- `CreatePromotion/DeactivatePromotion` no `OwnerWriteGuard` MEDIUM — model `saving/updating` enforces only when `promotions.features.owner.enabled` (disabled by default).
Performance:
- `withinCustomerLimit` O(promotions×orders) HIGH — each candidate re-chunks entire order history `:251-263` + PHP JSON parse `:276-298`. Hot cart path.
- Insights full-table loads HIGH — ~8 aggregates + `pluck(all):149` + `Order::get(all):203-206`.
- `DeactivateExpiredPromotionsCommand get()` LOW — `chunkById` + owner iteration.

### Prior-audit fix-first rows
| 28 | promotions | `Support/PromotionPerformanceInsights.php:114-123,203-206` | Owner-blind analytics + unscoped order load | HIGH |
| — | promotions | `DeactivateExpiredPromotionsCommand:23-26` | Cross-tenant sweep; one tenant's cron mutates others' | HIGH |

#### Verdicts: promotions

- [R2:P1] DOWNGRADED (was high) med bug | Services/PromotionService.php:209-274 | Fail-open x3 confirmed (guest/Pkg-missing/catch-all true); DUP AUD:B3, adopted audit MED.
- [R2:P2] CONFIRMED high perf | Services/PromotionService.php:175-263 | Per-promo order-history chunk+parse inside promo loop; no shared usage map. DUP AUD:B9.
- [R2:P3] CONFIRMED high sec+perf | Support/PromotionPerformanceInsights.php:114-206 | promotions() unscoped; Order count+get unbounded, PHP-side agg. DUP AUD:B6,B10,Q#1.
- [R2:P4] CONFIRMED high bug+sec | Console/Commands/DeactivateExpiredPromotionsCommand.php:23-42 | Unscoped get()+per-row update trips owner guard w/o context. Adopted audit HIGH (was med). DUP AUD:B7,B11,Q#2.
- [R2:P5] CONFIRMED med bug | Actions/CreatePromotion.php:13-15 | Raw $data to create; saving hook only normalizes code+conditions, no numeric/date checks.
- [R2:P6] CONFIRMED med bug | Actions/DeactivatePromotion.php:12-21 | fresh() null deref into dispatch; deactivated_at never set. DUP AUD:B1.
- [R2:P7] DOWNGRADED (was med) low sec | database/migrations/2000_12_01_000001_create_promotions_table.php:19 | Global unique code confirmed; DUP AUD:B4, adopted audit LOW.
- [R2:P8] CONFIRMED low bug | Models/Promotion.php:363-369 | Percent branch unclamped (round>price if value>100); fixed uses min. Distinct from AUD:B5 drift.
- [R2:P9] CONFIRMED low perf | Models/Promotion.php:60,192-214 | Static Schema-check cache process-wide, no reset; Octane/tenant-stale as claimed.
- [R2:P10] CONFIRMED low perf | database/migrations/2000_12_01_000001_create_promotions_table.php:48-53 | PK promo-side only; no (type,id) index for reverse lookups.
- [AUD:B1] ADOPTED med bug | Actions/DeactivatePromotion.php:14 | DUP R2:P6, counted once.
- [AUD:B2] ADOPTED med bug | Listeners/MarkPromotionAsUsedOnOrderPlaced.php:106 | No per-order dedup; OrderPaid redelivery re-increments usage.
- [AUD:B3] ADOPTED med bug | Services/PromotionService.php:209-274 | DUP R2:P1, counted once.
- [AUD:B4] ADOPTED low sec | database/migrations/2000_12_01_000001_create_promotions_table.php:19 | DUP R2:P7, counted once.
- [AUD:B5] ADOPTED low bug | Models/Promotion.php:366 | round() vs voucher intdiv (+5000,10000) 1c drift; both forms confirmed present.
- [AUD:B6] ADOPTED high sec | Support/PromotionPerformanceInsights.php:114-206 | DUP R2:P3, counted once.
- [AUD:B7] ADOPTED high sec | Console/Commands/DeactivateExpiredPromotionsCommand.php:23-26 | DUP R2:P4, counted once.
- [AUD:B8] ADOPTED med sec | Actions/CreatePromotion.php:13 | No OwnerWriteGuard in actions; model guards only when owner.enabled (default off).
- [AUD:B9] ADOPTED high perf | Services/PromotionService.php:251-263 | DUP R2:P2, counted once.
- [AUD:B10] ADOPTED high perf | Support/PromotionPerformanceInsights.php:149-206 | DUP R2:P3, counted once.
- [AUD:B11] ADOPTED low perf | Console/Commands/DeactivateExpiredPromotionsCommand.php:26 | DUP R2:P4, counted once.
- [AUD:Q#1] ADOPTED high sec+perf | Support/PromotionPerformanceInsights.php:114-206 | DUP R2:P3, counted once.
- [AUD:Q#2] ADOPTED high sec | Console/Commands/DeactivateExpiredPromotionsCommand.php:23-26 | DUP R2:P4, counted once.

### Prior-audit chunk: filament-vouchers

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-vouchers` LOW-if-gate / HIGH-if-bypass — `VoucherForm:395-436` editable `owner_type/id` mitigated by `enforceOwnerOnCreate/Update` + `VoucherAffiliateOwnershipGuard` when `owner.enabled`; confirm UI hides Ownership when disabled. Badges uncached. Activate/Pause/Redeem all `OwnerWriteGuard` GOOD.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-vouchers

- [R2:V1] CONFIRMED high sec | resources/views/widgets/voucher-suggestions.blade.php:74 | Code interpolated into wire:click quote; codes only trim+uppercase, no alpha-dash anywhere.
- [R2:V2] CONFIRMED high bug | src/Widgets/RedemptionTrendChart.php:41,126-133 | Public $filter unvalidated; per-day loop unbounded (huge filter = DoS).
- [R2:V3] CONFIRMED med sec | src/Actions/ManualRedeemVoucherAction.php:31-73 | visible() checks not rechecked in action; no min/parse-reject; no permission gate.
- [R2:V4] CONFIRMED med bug | src/Support/MoneyHelper.php:116-125 | decimalToInteger returns 0 on regex mismatch; garbage money becomes 0.
- [R2:V5] CONFIRMED med bug | src/Actions/BulkGenerateVouchersAction.php:90-111 | No txn; mb_strtoupper(nullable prefix) TypeError; Str::random no unique retry; count unclamped server-side.
- [R2:V6] CONFIRMED med sec | src/Resources/VoucherResource/Pages/CreateVoucher.php:37-56 | Owner enabled + no context => silent null/null global (promotions throws); form input overwritten.
- [R2:V7] CONFIRMED med perf | src/Widgets/VoucherSuggestionsWidget.php:79-118 | Unbounded get(); cart resolve + applied-vouchers per voucher in filter.
- [R2:V8] CONFIRMED med perf | src/Widgets/VoucherUsageTimelineWidget.php:74-124 | Timeline + summary each full-scan usages; sum/unique in PHP.
- [R2:V9] CONFIRMED med perf | src/Support/VoucherStatsAggregator.php:28-40 | 6 uncached counts/overview; wallet 6 counts + 7 daily whereDate, no cache/GROUP BY.
- [R2:V10] CONFIRMED low perf | src/Exports/VoucherUsageExporter.php:22-84 | resolve() ~5x/row + orderId/orderNumber per row; owner-scope inheritance needs integration runtime check.
- [R2:V11] CONFIRMED low bug | src/Resources/VoucherResource/Pages/EditVoucher.php:54-77 | ConditionTarget::from unguarded; fromArray throws on bad legacy definition.
- [R2:V12] CONFIRMED low bug | src/Resources/VoucherResource/Schemas/VoucherForm.php:54,85-375 | Global unique code; percent value no max; upline (int) truncates decimals.
- [R2:V13] CONFIRMED med perf | src/Resources/VoucherResource.php:114-119 | Uncached badge counts both resources. Adopted audit MED (was low). DUP AUD:B3.
- [AUD:B1] ADOPTED low sec | src/Resources/VoucherResource/Schemas/VoucherForm.php:390-440 | Owner guards confirmed; Ownership UI gated on registry not owner.enabled, hide-when-disabled unconfirmed.
- [AUD:B2] ADOPTED info nav | src/Resources/VoucherResource.php:150-158 | G1 PASS (config group/sort, no static group); light confirm only.
- [AUD:B3] ADOPTED med perf | src/Resources/VoucherResource.php:114-119 | G2 uncached badges. DUP R2:V13, counted once.
- [AUD:B4] ADOPTED med perf | src/Resources/VoucherResource/Tables/VouchersTable.php:1 | G3 N+1; per-table eager-loads not re-checked, plausible.
- [AUD:B5] ADOPTED med perf | src/Resources/VoucherResource/Schemas/VoucherForm.php:246-259 | G4; VoucherForm uses relationship()/search APIs, no pluck pattern seen.
- [AUD:B6] ADOPTED low-med perf | src/Support/MoneyHelper.php:1-20 | G5; MoneyNormalizer overlap plausible per docblock; widget math at :223-228 present.
- [AUD:B7] ADOPTED med perf | audits/.staging-verify/vpkg/audit-filament-vouchers.md:8 | G6 scoped to cashier-chip/inventory; N/A here.

### Prior-audit chunk: filament-ticketing

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-ticketing` GOOD — all `OwnerUiScope+whereHas(pass)+with()`. Verify `TicketTypeResource:71` `MorphToSelect ticketable` per-type scoping LOW.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-ticketing

- [R2:T1] CONFIRMED med sec | src/Resources/TicketTypeResource.php:71-80 | MorphToSelect unscoped per-type; guard checks existence only, cross-owner attach persists. DUP AUD:B1, kept med.
- [R2:T2] CONFIRMED med bug | src/Resources/TicketTypeResource.php:86-126 | Code no unique; admits no min; min/max uncrossed; dates unordered; price no min; currency free text.
- [R2:T3] CONFIRMED med bug | src/Resources/TicketTypeResource.php:98-103 | Float price + '$' vs core integer cast truncates decimals; USD default vs repo MYR.
- [R2:T4] CONFIRMED med sec | src/Resources/TicketTypeResource.php:1-32 | Zero can* methods in Resources dir; any panel user can CRUD ticket types.
- [R2:T5] CONFIRMED low perf | src/Resources/TicketTypeResource/RelationManagers/TicketTypeProductsRelationManager.php:21 | Nested product/componentTicketType.name cols, no eager loads; N+1.
- [AUD:B1] ADOPTED med sec | src/Resources/TicketTypeResource.php:71 | DUP R2:T1, counted once; kept med (TicketingOwnerGuard existence-only).
- [AUD:B2] ADOPTED info nav | src/Resources/TicketTypeResource.php:38-48 | G1 PASS (config group/sort); light confirm only.
- [AUD:B3] ADOPTED info perf | src/Resources/:1 | G2 N/A here: no getNavigationBadge found in this package.
- [AUD:B4] ADOPTED med perf | src/Resources/TicketTypeResource/RelationManagers/:1 | G3; Pass positive per audit, RM N+1 DUP R2:T5.
- [AUD:B5] ADOPTED med perf | src/Resources/TicketTypeResource.php:71-80 | G4; no Select-pluck pattern in TicketTypeResource (MorphToSelect only).
- [AUD:B6] ADOPTED info perf | audits/.staging-verify/vpkg/audit-filament-ticketing.md:7 | G5 vouchers-scoped; N/A here.
- [AUD:B7] ADOPTED med perf | audits/.staging-verify/vpkg/audit-filament-ticketing.md:8 | G6 scoped to cashier-chip/inventory; N/A here.

---

## Round 2 — `filament-tax`, `shipping`, `growth`, `filament-growth`

### E2E findings (verbatim)

End-to-end review: filament-tax, shipping, growth, filament-growth. 26 findings + positives. All paths relative to /Users/Saiffil/Herd/commerce.

## SHIPPING (packages/shipping)

S1 — HIGH/security — packages/shipping/src/Policies/ShippingZonePolicy.php:32-77 — Zone policy lacks owner boundary (cross-tenant IDOR). view/view/update/delete/manageRates check only `shipping.zones.*` permission, while ShipmentPolicy (ShipmentPolicy.php:65-80) and ReturnAuthorizationPolicy (ReturnAuthorizationPolicy.php:64-79) both add an `isOwner()` check when `shipping.features.owner.enabled` with explicit "prevent cross-tenant IDOR" comments. Any user with zone permissions can read/update/delete another owner's zones. Evidence: `public function update(...){ return $this->hasPermission($user,'shipping.zones.update'); }` vs ShipmentPolicy's owner check. Recommendation: copy the `isOwner()` + include_global pattern from ShipmentPolicy into all ShippingZonePolicy record methods. Confidence: high.

S2 — MEDIUM/security — packages/shipping/src/Integrations/OrderFulfillmentHandler.php:224 — getTracking() tracking-number oracle. `Shipment::where('tracking_number',$trackingNumber)->first()` relies solely on the global scope; with owner mode off it returns any shipment's status/events, and tracking numbers are low-entropy `uniqid()` (ManualShippingDriver.php:96 `MAN-`.mb_strtoupper(uniqid()); ZoneBasedShippingDriver.php:118 same pattern), enumerable. Recommendation: scope lookup to the requesting order/customer or require an unguessable token; use Str::ulid/random for tracking refs. Confidence: med.

S3 — MEDIUM/bug — packages/shipping/src/Actions/ApproveReturnAuthorization.php:27-34; RejectReturnAuthorization.php:27-34 — RMA approve/reject bypass the Spatie state machine via direct `$rma->update(['status'=>'approved'/'rejected',...])`, skipping transition validation and transition events (compare UpdateShipmentStatus/ShipShipment which use transitionTo). Mitigated by the isPending() guard. Recommendation: use `$rma->status->transitionTo(RmaApproved::class)` (+ timestamps). Confidence: med.

S4 — MEDIUM/performance — packages/shipping/src/Services/TrackingAggregator.php:163-168 (same in RecordTrackingEvent.php:39-42) — per-event `exists()` query inside loop; syncBatch compounds it per shipment. Evidence: `foreach ($events as $eventData) { $exists = $shipment->events()->where(...)->where(...)->exists(); ...}`. Recommendation: one `whereIn` fetch of existing (code, occurred_at) pairs per shipment. Confidence: high.

S5 — LOW/bug — packages/shipping/src/Services/RateShoppingEngine.php:149-156 — clearCache() is a silent no-op on non-taggable cache stores (file/database): only flushes inside `instanceof TaggableStore`. Recommendation: track keys or document taggable-store requirement; TTL 300 bounds staleness. Confidence: high.

S6 — LOW/bug — packages/shipping/src/Models/ShippingZone.php:242 — postcode range compare is lexicographic (`$postcode >= $from && $postcode <= $to`), so e.g. '50000' matches '1000'-'9999'. Low impact for fixed-length MY postcodes. Recommendation: numeric compare when both sides are digits, else length-aware compare. Confidence: med.

S7 — LOW/security — packages/shipping/src/Integrations/OrderFulfillmentHandler.php:309-313,339-349 — forced `location_id` from $shipmentData resolved via unscoped `InventoryLocation::find()`, never checked against the order's owner. Recommendation: scope to owner's locations. Confidence: med (caller input trust unknown).

S8 — LOW/performance — packages/shipping/src/Actions/RecalculateShipmentWeight.php:17 — `items()->get()->sum(...)` hydrates all items; CreateShipment.php:74 uses SQL `sum(weight*quantity)`. Recommendation: match the SQL aggregate. Confidence: high.

S9 — LOW/bug — packages/shipping/src/Models/ShipmentOperation.php:52-75 — recordStart() check-then-insert has no unique index; concurrent callers can double-create Pending rows. Mitigated by Cache locks in Ship/CancelShipment. Recommendation: unique index on (shipment_id, operation_type, status) or insert-catch. Confidence: med.

S10 — LOW/performance — packages/shipping/src/Services/BatchRateLimiter.php:191-224 — rate key has no owner/tenant segment (cross-tenant contention) and `sleep($retryAfter)` blocks the request up to 30s. Recommendation: include owner scope in key; move bulk sync off-request. Confidence: med.

## GROWTH (packages/growth)

G1 — MEDIUM/bug — packages/growth/src/Console/Commands/RecomputeExperimentAssignmentsCommand.php:29; ArchiveExperimentsCommand.php:31 — wrong owner config key: `new OwnerBatchRunner(X::class, ['enabled'=>'commerce-support.owner.enabled'])`, but growth's flag is `growth.features.owner.enabled` (config/growth.php:78-82). OwnerBatchRunner::isOwnerDisabled() (commerce-support/src/Support/OwnerBatchRunner.php:81-85) therefore mis-detects: growth-owner-on + commerce-key-off runs the callback with no owner iteration (global OwnerScope then throws, command crashes); inverse runs N redundant passes. Recommendation: use `growth.features.owner.enabled`. Confidence: high (mismatch verified; crash path med).

G2 — MEDIUM/performance — packages/growth/src/Actions/AggregateExperimentMetrics.php:60-66 — handle() loads ALL assignments and ALL matching signal events unbounded (`->get()`), per experiment; handleMany's UNION is likewise unbounded. Recommendation: chunk/stream or cap with explicit windowing. Confidence: high.

G3 — MEDIUM/performance — RecomputeExperimentAssignmentsCommand.php:34-41 + ResolveExperimentAssignment.php:248-266 — repair loop calls variantForSubject() → pickVariant() → full variant query PER assignment (100k assignments = 100k queries). Recommendation: cache active variants per experiment_id for the run. Confidence: high.

G4 — LOW/bug — packages/growth/src/Support/Context/ExperimentResolver.php:23-43 vs :166-173 — resolve() accepts ResolveStrategy but never applies the Readable filter (only resolveBySlug does), so AggregateExperimentMetrics and BuildExperimentSignalProperties pass Readable expecting active-only and silently get any status. Recommendation: apply applyReadableFilter() in resolve() or remove the dead parameter. Confidence: high.

G5 — LOW/bug — packages/growth/src/Support/ExperimentAssignmentResolver.php:143-149 vs Actions/ResolveExperimentAssignment.php:98-113 — attribution builds raw `'anonymous:'.$id` but storage hashes IDs over ~245 chars (`anonymous:sha256:...`), so long anonymous IDs never match → unattributed events. Recommendation: share one canonicalizer. Confidence: med.

G6 — LOW/robustness — packages/growth/src/Http/Middleware/ResolveExperiment.php:37-44 — catches InvalidArgumentException but not AuthorizationException from resolveBySlug(), so a cross-owner slug yields a storefront 403/500 instead of skipping. Recommendation: also skip on AuthorizationException (or fail closed deliberately with logging). Confidence: med.

G7 — LOW/bug — packages/growth/src/Console/Commands/ArchiveExperimentsCommand.php:25,36-41 — unbounded `->get()` and unvalidated `--older-than` (negative → future threshold → archives all concluded). Recommendation: chunk + `max(0,...)`/validate. Confidence: high mechanics, low impact (console operator).

G8 — LOW/bug — packages/growth/src/Actions/AggregateExperimentMetrics.php:238-254 — handleMany uses Postgres-only `CAST(NULL AS uuid/timestamptz)`; breaks MySQL/SQLite despite `commerce_json_column_type` multi-DB support. Recommendation: driver-aware casts. Confidence: med.

G9 — LOW/correctness — packages/growth/src/Support/Http/DefaultRequestExperimentSubjectResolver.php:69-73 — external_id fallback lookup lacks the auth_user_type filter the primary query has → colliding identifiers across user types merge assignments. Recommendation: add morph-class filter. Confidence: low-med.

G10 — LOW/performance — packages/growth/src/Models/Assignment.php:131-145 — every save (including last_seen_at touches) re-resolves experiment+variant+identity+session (up to 4 queries) on the hot assignment path. Recommendation: skip consistency check when only timestamps/metadata changed. Confidence: med.

## FILAMENT-TAX (packages/filament-tax)

T1 — HIGH/security — TaxZonesTable.php:68-71; TaxRatesTable.php:107-110; TaxClassesTable.php:57-59; TaxExemptionsTable.php:135-183 (DeleteAction.php:181 bare); RatesTable.php:50-56 — row-level View/Edit/Delete/Create actions have NO ->authorize(), resources define no can*/policy overrides, and no policies exist anywhere in tax or filament-tax (verified by search); only bulk actions check `tax.*` permissions. Filament default-allows without policies, so any panel user can create/edit/delete tax config and reach `/{record}/edit` URLs directly. Recommendation: add ->authorize('tax.zones.update' etc.) to every row/header action + canCreate/canEdit/canDelete on resources (or register policies). Confidence: med-high (all code facts verified; Filament default-allow not re-verified in vendor since vendor isn't searchable).

T2 — MEDIUM/bug — packages/filament-tax/src/Pages/ManageTaxSettings.php:143-170 — save() persists `$this->data` directly, never `$this->form->getState()`, so numeric bounds (defaultTaxRate 0-100), required, and Select-option constraints are unenforced server-side (unbounded rate, arbitrary taxIdLabel). Recommendation: `$state = $this->form->getState()` + validation rules. Confidence: high.

T3 — LOW/security — TaxExemptionForm.php:52-123 — exemptable_id search + option-label queries use raw `$type::query()`, relying on the customers package's global scope; inconsistent with the taxZone relationship in the same form which uses OwnerUiScope::apply (line 130). Cross-owner customer name/email disclosure if customers owner mode is off. Create/edit revalidation (CreateTaxExemption.php:27-43) covers writes only. Recommendation: wrap in OwnerUiScope::apply like taxZone. Confidence: med.

T4 — LOW/bug — TaxExemptionForm.php:141-144 — certificate_number `->unique(ignoreRecord:true)` is global, not owner-scoped (unlike zone code at TaxZoneForm.php:38-50) → cross-tenant collision blocks creation. Confidence: high mechanics, low impact.

T5 — LOW/security — TaxExemptionsTable.php:156-182 — row approve/renew/delete act on $record without the OwnerWriteGuard revalidation their bulk siblings perform. Relies on the scoped table query. Recommendation: re-verify per record. Confidence: med.

T6 — LOW/performance — TaxExemptionsTable.php:34,48; ExpiringExemptionsWidget.php:27 — `exemptable.*`/`taxZone.name` columns with no eager loading → per-row queries (morph). Recommendation: eager-load where possible / accept for morph. Confidence: med.

## FILAMENT-GROWTH (packages/filament-growth)

FG1 — MEDIUM/security — packages/filament-growth/src/Pages/ManageGrowthSettings.php:51-58,89-98 — global experiment-middleware kill switch gated only by `viewAny Experiment`; any experiment viewer can disable all request-time assignment. No dedicated permission (contrast tax.settings.manage). Recommendation: add `growth.settings.manage` permission + HasPageAuthz-style gate. Confidence: high.

FG2 — MEDIUM/performance — packages/filament-growth/src/Widgets/ExperimentWinnersWidget.php:42-81 — up to 5 sequential full AggregateExperimentMetrics::handle() calls (each loading all assignments+events per G2) instead of the existing handleMany() batch path. Recommendation: use handleMany(). Confidence: high.

FG3 — LOW/performance — ExperimentResultsPage.php:261-268 — experimentOptions() loads ALL experiments into a preloaded Livewire select, unbounded. Recommendation: paginate/lazy-search the select. Confidence: high.

FG4 — LOW/bug — ExperimentResultsPage.php:135,149,263,286; ExperimentWinnersWidget.php:44; VariantsTable.php:39 — raw `Experiment::query()` relying solely on the global scope, bypassing this package's own TrackedProperty-aware resolution (VariantForm::scopeAccessibleExperiments). Safe today only because GrowthServiceProvider.php:73-87 boots-hard on growth/signals owner-mode mismatch. Recommendation: use OwnerUiScope::apply / scopeAccessibleExperiments for consistency. Confidence: med.

FG5 — LOW/bug — Support/ExperimentHelpers.php:36-39 — canDeleteAnyExperiment() unconditionally true; bulk delete always visible (per-record canMutateRecord fail-closes, so only UX noise). Recommendation: mirror ExperimentPolicy::deleteAny. Confidence: high mechanics, low impact.

## Positives (brief)
- Shipping: LabelController defense-in-depth (signed URL + token + auth user + owner match); Shipment/RMA policies pair permissions with owner boundary + state guards; Ship/Cancel idempotent (Cache locks + ShipmentOperation ledger + retry with backoff/jitter); ShippingZoneResolver owner-keyed per-request cache with Octane-scoped bindings; CreateShipment enforces owner context; cart rate selection re-matches live engine quotes (no client price trust); migrations indexed, uuid PKs, no FK/SoftDeletes.
- Growth: strong owner hygiene (ExperimentResolver, Assignment/Variant parent-consistency guards, ScopeSignalQueryToOwner, OwnerWriteGuard); sticky assignments with lockForUpdate + unique-violation retry; tracked_property_id immutability; owner-keyed request caches; ExperimentContextManager singleton holds no state (Octane-safe).
- Filament-tax: bulk actions re-verify via OwnerWriteGuard; exemptable revalidation on create/edit; certificate download has traversal guard + owner visibility check + private disk + restricted upload types; owner-scoped zone-code uniqueness; all blades escaped.
- Filament-growth: Experiment/Variant policies + canEdit/canDelete overrides; variant settings allowlist normalization wired into create/edit; GrowthStatsAggregator batched UNION + window functions with safe fallbacks; blades escaped; no `{!!}` anywhere.
- Cross-cutting: money in int minor units; no DB FK/cascades/SoftDeletes; uuid PKs; no SSRF/Http-client, deserialization, or injection sinks found in scope; repo-level Pest coverage exists (tests/src/Shipping, Growth, Tax).

## Not verified / unresolved
- Filament v5 default-allow without policies (vendor/ not searchable) — T1 severity hinges on it; code-side facts (no authorize, no policies, no can* overrides) are verified.
- Whether any storefront/guest route reaches OrderFulfillmentHandler::getTracking (orders package out of scope) — affects S2 exploitability.
- handleMany CAST portability (G8) against the repo's actually-supported DB list.

### Prior-audit chunk: filament-tax

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-tax` GOOD exemplary polymorphic — scoped + relationship options scoped + create/edit re-validate only if target `HasOwner`.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-tax

- [T1] CONFIRMED HIGH security | packages/filament-tax/src/Resources/TaxZoneResource/Tables/TaxZonesTable.php:68-71 | all row/header CRUD bare, no policies/can*; vendor helpers.php:92 default-allows; only custom approve/renew/download authed
- [T2] CONFIRMED MEDIUM bug | packages/filament-tax/src/Pages/ManageTaxSettings.php:143-170 | save() persists $this->data, never form->getState(); rate bounds/required/options unenforced
- [T3] CONFIRMED LOW security | packages/filament-tax/src/Resources/TaxExemptionResource/Schemas/TaxExemptionForm.php:52-123 | exemptable search/label use raw $type::query(); taxZone in same form uses OwnerUiScope:130
- [T4] CONFIRMED LOW bug | packages/filament-tax/src/Resources/TaxExemptionResource/Schemas/TaxExemptionForm.php:141-144 | certificate_number unique() global, not owner-scoped like zone code TaxZoneForm:38-50
- [T5] CONFIRMED LOW security | packages/filament-tax/src/Resources/TaxExemptionResource/Tables/TaxExemptionsTable.php:156-182 | row approve/renew/delete skip OwnerWriteGuard bulk siblings use (perm authorize present); scoped query only guard
- [T6] CONFIRMED MEDIUM performance | packages/filament-tax/src/Resources/TaxExemptionResource/Tables/TaxExemptionsTable.php:34 | DUP AUD:B4, adopt MEDIUM; exemptable/taxZone columns w/o eager load; widget:27 same
- [AUD:B1] ADOPTED info note | packages/filament-tax/src/Resources/TaxExemptionResource/Schemas/TaxExemptionForm.php:125-131 | GOOD exemplary polymorphic scoping (OwnerUiScope on taxZone); no action
- [AUD:B2] ADOPTED info note | packages/filament-tax/src/Resources/TaxZoneResource.php:26-36 | G1 navigation PASS: config-based group/sort, no static group; no action
- [AUD:B3] ADOPTED MEDIUM performance | packages/filament-tax/src/Resources/TaxExemptionResource.php:69-80 | G2 uncached expiring-count per nav render; add OwnerCache 30s
- [AUD:B4] ADOPTED MEDIUM performance | packages/filament-tax/src/Resources/TaxExemptionResource/Tables/TaxExemptionsTable.php:34 | G3 relation columns, getEloquentQuery:43-47 has no with(); DUP T6, counted once
- [AUD:B5] FALSE MEDIUM performance | packages/filament-tax/src/Resources/TaxExemptionResource/Schemas/TaxExemptionForm.php:40 | G4: no pluck()+preload site in package; all options() static arrays/enums/closures
- [AUD:B6] FALSE LOW note | packages/filament-tax/src/Resources/TaxZoneResource.php:20 | G5 domain leakage cites vouchers widgets only; no tax-local site in full-src search
- [AUD:B7] FALSE MEDIUM performance | packages/filament-tax/src/Resources/TaxZoneResource.php:20 | G6 collection sums cite cashier-chip/inventory only; no tax-local site found

### Prior-audit chunk: shipping

### Prior-audit section
### shipping
Bugs:
- `CreateShipment:77` HIGH — `event(new ShipmentCreated)` inside txn, no `afterCommit` (orders uses `afterCommit:143-145`); ghost waybill on rollback.
- `ShippingRate:186-194` MEDIUM — fail-open `default => true`; should fail-closed.
- `ShippingRate:294` MEDIUM — `(int)(total*rate/10000)` truncation vs cart half-up (`CartMoney:51-61`).
Security:
- `OrderFulfillmentHandler:224` HIGH — `Shipment::where(tracking_number)->first()` no `forOwner`; enumerable tracking → disclosure. Scope or signed URL (copy jnt `AwbController`).
- `OrderFulfillmentHandler:288,345` HIGH — `location_id` via bare `InventoryLocation::find()`; ships from + leaks foreign warehouse.
- `Shipment:72-74 owner_*` fillable note — `CreateShipment:29-37` rejects mismatch (keep guard; remove from fillable). Label route is token+auth+owner-match (not `signed` — jnt AWB is the signed reference).
Performance:
- `RecalculateShipmentWeight:17-19` hydrates MEDIUM — use SQL `sum(weight*quantity)` (already used `CreateShipment:74`).
- Triple item scan MEDIUM — eager-load `items` once (copy `GenerateCheckoutDocumentsJob:72`).
- Driver fan-out MEDIUM (narrowed) — default parallel via `Concurrency`; serial only in fallback; no timeout/circuit in either. Add timeout/circuit + short-TTL cache.

### Prior-audit fix-first rows
| — | shipping | `CreateShipment:77` | Event before commit → ghost waybill on rollback | HIGH |
| 37 | shipping | `Integrations/OrderFulfillmentHandler.php:224,288,345` | Unscoped tracking lookup + unvalidated `location_id` | HIGH |

#### Verdicts: shipping

- [S1] CONFIRMED HIGH security | packages/shipping/src/Policies/ShippingZonePolicy.php:32-77 | no owner check in view/update/delete/manageRates vs ShipmentPolicy isOwner; cross-tenant IDOR when owner mode on
- [S2] CONFIRMED HIGH security | packages/shipping/src/Integrations/OrderFulfillmentHandler.php:224 | DUP AUD:B4, adopt HIGH; unscoped tracking lookup + uniqid refs; caller-route reachability unverified
- [S3] CONFIRMED MEDIUM bug | packages/shipping/src/Actions/ApproveReturnAuthorization.php:27-34 | Approve/Reject direct-update status, skipping Spatie transitions/events; isPending guard only mitigation
- [S4] CONFIRMED MEDIUM performance | packages/shipping/src/Services/TrackingAggregator.php:163-168 | per-event exists() inside loop, xN via syncBatch; RecordTrackingEvent:39-42 same check single-event
- [S5] CONFIRMED LOW bug | packages/shipping/src/Services/RateShoppingEngine.php:149-156 | clearCache silent no-op on non-taggable stores; only TTL 300 bounds staleness
- [S6] CONFIRMED LOW bug | packages/shipping/src/Models/ShippingZone.php:242 | lexicographic postcode range compare ('50000' in '1000'-'9999'); low impact for fixed-length MY codes
- [S7] CONFIRMED HIGH security | packages/shipping/src/Integrations/OrderFulfillmentHandler.php:345 | DUP AUD:B5, adopt HIGH; forced location_id via bare find, no order-owner check; global scope is ambient-only
- [S8] CONFIRMED MEDIUM performance | packages/shipping/src/Actions/RecalculateShipmentWeight.php:17 | DUP AUD:B7, adopt MEDIUM; hydrates all items; use SQL sum(weight*quantity) as CreateShipment:74
- [S9] CONFIRMED LOW bug | packages/shipping/src/Models/ShipmentOperation.php:52-75 | check-then-insert w/o unique index (migration has plain indexes only); Cache locks in Ship/Cancel mitigate
- [S10] CONFIRMED LOW performance | packages/shipping/src/Services/BatchRateLimiter.php:191-224 | rate key has no owner segment (per-carrier prefix only); sleep() blocks request up to 30s
- [AUD:B1] ADOPTED HIGH bug | packages/shipping/src/Actions/CreateShipment.php:77 | event(new ShipmentCreated) inside txn, no afterCommit; ghost waybill on rollback; DUP AUD:Q1
- [AUD:B2] ADOPTED MEDIUM bug | packages/shipping/src/Models/ShippingRate.php:186-194 | unknown condition type fail-open via default=>true; should fail closed
- [AUD:B3] ADOPTED MEDIUM bug | packages/shipping/src/Models/ShippingRate.php:294 | (int) truncation on percentage rate vs cart half-up rounding
- [AUD:B4] ADOPTED HIGH security | packages/shipping/src/Integrations/OrderFulfillmentHandler.php:224 | unscoped tracking lookup, enumerable refs; DUP S2, counted once
- [AUD:B5] ADOPTED HIGH security | packages/shipping/src/Integrations/OrderFulfillmentHandler.php:345 | unvalidated location_id ships from foreign warehouse; DUP S7, counted once
- [AUD:B6] ADOPTED LOW security | packages/shipping/src/Models/Shipment.php:72-74 | owner_* fillable; CreateShipment:29-37 mismatch guard holds but remove from fillable
- [AUD:B7] ADOPTED MEDIUM performance | packages/shipping/src/Actions/RecalculateShipmentWeight.php:17 | hydrates items for sum; DUP S8, counted once
- [AUD:B8] UNVERIFIED MEDIUM performance | packages/shipping/src/Actions/CreateShipment.php:74 | no audit file:line cited; no 3x-scan site in current src; need cited location to confirm
- [AUD:B9] ADOPTED MEDIUM performance | packages/shipping/src/Services/RateShoppingEngine.php:173-243 | parallel default + serial fallback confirmed; no timeout/circuit in either path
- [AUD:Q1] ADOPTED HIGH bug | packages/shipping/src/Actions/CreateShipment.php:77 | fix-first row merges AUD:B1, counted once
- [AUD:Q2] ADOPTED HIGH security | packages/shipping/src/Integrations/OrderFulfillmentHandler.php:224 | fix-first row merges AUD:B4+AUD:B5, counted once

### Prior-audit chunk: growth

### Prior-audit section
### growth
Bugs:
- `ResolveExperimentAssignment:248-257` MEDIUM — `variantForSubject` skips `resolveExperimentForCurrentOwner` (verify callers).
- Multi-currency no FX MEDIUM — `MetricsCalculator:217-221` filters to experiment currency, drops rest silently.
Security: clean — signal binding `validateSignalReferences:410-450` + explicit-global gate; no `owner_*` fillable.
Performance:
- `AggregateExperimentMetrics:60-66` unbounded `get()` MEDIUM — route dashboards via bounded/`handleMany` UNION (`:230-327`).
- `pickVariant:259-266` re-queries per assignment LOW — cache per request.
- `handleMany` 3-query UNION + request cache GOOD.

#### Verdicts: growth

- [G1] CONFIRMED MEDIUM bug | packages/growth/src/Console/Commands/RecomputeExperimentAssignmentsCommand.php:29 | OwnerBatchRunner key commerce-support.owner.enabled vs growth.features.owner.enabled; both commands affected
- [G2] CONFIRMED MEDIUM performance | packages/growth/src/Actions/AggregateExperimentMetrics.php:60-66 | unbounded get() of assignments+events per experiment; DUP AUD:B4 (FALSE-list: perf only, kept)
- [G3] CONFIRMED MEDIUM performance | packages/growth/src/Actions/RepairExperimentAssignment.php:40 | DUP AUD:B5; variantForSubject full variant query per assignment in repair loop; loop scope keeps MEDIUM
- [G4] CONFIRMED LOW bug | packages/growth/src/Support/Context/ExperimentResolver.php:23-43 | resolve() takes Readable but never filters (only resolveBySlug does); callers get any status
- [G5] CONFIRMED LOW bug | packages/growth/src/Support/ExperimentAssignmentResolver.php:143-149 | raw 'anonymous:'.$id vs hashed storage when key>255 chars; long anonymous IDs never match
- [G6] CONFIRMED LOW robustness | packages/growth/src/Http/Middleware/ResolveExperiment.php:37-44 | catches InvalidArgument only; resolveBySlug AuthorizationException escapes to storefront 403/500
- [G7] CONFIRMED LOW bug | packages/growth/src/Console/Commands/ArchiveExperimentsCommand.php:25-41 | unbounded get() + unvalidated --older-than; negative value archives all concluded
- [G8] CONFIRMED LOW bug | packages/growth/src/Actions/AggregateExperimentMetrics.php:241-249 | Postgres-only CAST uuid/timestamptz; MySQL syntax error; repo is multi-DB (json-type switching)
- [G9] CONFIRMED LOW correctness | packages/growth/src/Support/Http/DefaultRequestExperimentSubjectResolver.php:69-73 | external_id fallback lacks auth_user_type filter of primary query; cross-type id collision merges
- [G10] CONFIRMED LOW performance | packages/growth/src/Models/Assignment.php:131-145 | every save re-resolves experiment+variant+identity+session; no skip for timestamp-only touches
- [AUD:B1] ADOPTED MEDIUM bug | packages/growth/src/Actions/ResolveExperimentAssignment.php:248-257 | variantForSubject skips resolveExperimentForCurrentOwner that handle():43 performs; verify callers
- [AUD:B2] ADOPTED MEDIUM bug | packages/growth/src/Support/MetricsCalculator.php:124-129 | no FX: non-experiment-currency revenue filtered out silently via eventMatchesCurrency
- [AUD:B3] ADOPTED info note | packages/growth/src/Actions/ResolveExperimentAssignment.php:410-450 | security-clean note holds: binding validation + explicit-global gate; no action
- [AUD:B4] ADOPTED MEDIUM performance | packages/growth/src/Actions/AggregateExperimentMetrics.php:60-66 | unbounded get(); route dashboards via handleMany; DUP G2, counted once
- [AUD:B5] ADOPTED LOW performance | packages/growth/src/Actions/ResolveExperimentAssignment.php:259-266 | pickVariant re-queries per assignment; DUP G3 (loop scope there keeps MEDIUM), counted once
- [AUD:B6] ADOPTED info note | packages/growth/src/Actions/AggregateExperimentMetrics.php:229-327 | handleMany 3-query UNION + request cache GOOD; no action

### Prior-audit chunk: filament-growth

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-growth` GOOD — scoped + constrained eager; variant form delegates to `scopeAccessibleExperiments`.
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-growth

- [FG1] CONFIRMED MEDIUM security | packages/filament-growth/src/Pages/ManageGrowthSettings.php:51-58 | global middleware kill switch gated only by viewAny Experiment; no dedicated settings permission
- [FG2] CONFIRMED MEDIUM performance | packages/filament-growth/src/Widgets/ExperimentWinnersWidget.php:42-81 | up to 5 sequential full handle() calls (each unbounded per G2) instead of handleMany()
- [FG3] CONFIRMED MEDIUM performance | packages/filament-growth/src/Pages/ExperimentResultsPage.php:104-110 | DUP AUD:B5, adopt MEDIUM; unbounded experimentOptions:263 into preloaded searchable select
- [FG4] CONFIRMED LOW bug | packages/filament-growth/src/Pages/ExperimentResultsPage.php:135 | raw Experiment::query at :135,:149,:263,:286 + VariantsTable:39,94 bypasses scopeAccessibleExperiments
- [FG5] CONFIRMED LOW bug | packages/filament-growth/src/Support/ExperimentHelpers.php:36-39 | canDeleteAnyExperiment() unconditionally true; UX noise only, per-record fail-close holds
- [AUD:B1] ADOPTED info note | packages/filament-growth/src/Resources/VariantResource/Schemas/VariantForm.php:176-182 | GOOD: scopeAccessibleExperiments via OwnerUiScope + constrained eager; no action
- [AUD:B2] ADOPTED info note | packages/filament-growth/src/Resources/ExperimentResource.php:50-53 | G1 navigation PASS: config-based group; no action
- [AUD:B3] FALSE MEDIUM performance | packages/filament-growth/src/Resources/ExperimentResource.php:50 | G2: no getNavigationBadge in package; uncached-badge note N/A here
- [AUD:B4] ADOPTED LOW performance | packages/filament-growth/src/Resources/VariantResource/Tables/VariantsTable.php:94 | G3: resource with() experiment:38 covers table; per-row fallback query only when unloaded
- [AUD:B5] ADOPTED MEDIUM performance | packages/filament-growth/src/Pages/ExperimentResultsPage.php:104-110 | G4 unbounded options into preloaded select; DUP FG3, counted once
- [AUD:B6] FALSE LOW note | packages/filament-growth/src/Resources/ExperimentResource.php:50 | G5 domain leakage cites vouchers only; no growth-local site found
- [AUD:B7] FALSE MEDIUM performance | packages/filament-growth/src/Resources/ExperimentResource.php:50 | G6 collection sums cite cashier-chip/inventory only; no growth-local site

---

## Round 2 — `membership`, `moderation`

### E2E findings (verbatim)

End-to-end review: packages/membership + packages/moderation. 23 findings (13 membership, 10 moderation), all from inspected bodies.

MEMBERSHIP

M1 | HIGH | bug | packages/membership/src/Actions/InviteMemberAction.php:39-45 + src/Models/MembershipInvitation.php:195-204
Expired-but-Pending invitations deadlock re-invite; no expiry sweeper exists. pendingInvitationQuery filters status=Pending only, ignoring expires_at; expireIfDue() has zero src callers (tests only) and no command/scheduler ships (unlike moderation:expire-blocks). After expiry, re-invite returns the dead row with no event/token, and accept fails isValid(). Docs (04-usage.md:132) promise a new invite after terminal status, but nothing transitions it.
Evidence: `->where('status', InvitationStatus::Pending)` … `if ($existing instanceof MembershipInvitation) { return $existing; }`
Recommend: expire-if-due the existing row (or exclude past-expires_at) in InviteMemberAction; ship membership:expire-invitations + scheduler docs. Confidence: high.

M2 | MEDIUM | security | packages/membership/src/Actions/AcceptInvitationAction.php:21-44
Accept performs no token proof; matchesToken() has zero src callers. Safety depends entirely on the host resolving the invitation via token-hash lookup as docs prescribe (04-usage.md:98-102). Any host resolving by id/UUID (e.g. route-model binding) gets accept gated only by email match.
Evidence: `handle(MembershipInvitation $invitation, Model $user)` — no token param; email check only.
Recommend: add optional token param verified with hash_equals, or an AcceptInvitationByTokenAction. Confidence: high.

M3 | MEDIUM | bug | ApproveMembershipApplicationAction.php:49-53 + :64-70; ChangeMemberRoleAction.php:37-46
Duplicate/spurious MembershipHook events. Approve fires onMemberAdded inside AddMemberAction then again directly (2x). Role change fires onMemberAdded (via handleResolvedMember) plus onMemberRoleChanged — a change looks like an add. Host notifications/billing double-fire.
Recommend: add suppress flag on the role-change path; remove duplicate dispatch in Approve. Confidence: high.

M4 | MEDIUM | bug | ApproveMembershipApplicationAction.php:49-53; migration 000001:22
Approve TypeErrors when applicant or subject is gone. applicant_id is nullable and repo rules forbid FK cascades, so deleted users leave orphans; `$lockedApplication->applicant`/`->subject` null then violates `Model` typehints in AddMemberAction/MembershipSubjectGuard → 500.
Recommend: explicit null checks throwing a domain exception. Confidence: high.

M5 | MEDIUM | bug | MembershipRoleSyncService.php:118-124 via RemoveMemberAction.php:42-44, AddMemberAction.php:79-81
revokeFromUser throws RoleDoesNotExist when the mapped Spatie role row is missing in that team (verified: vendor HasRoles removeRole→collectRoles→getStoredRole→findByName throws). Runs inside the caller's transaction, so member removal / role change rolls back entirely for legacy members, team-scope toggles, or pruned roles.
Recommend: guard with hasRole() or catch RoleDoesNotExist (detach is already a no-op when unassigned). Confidence: high.

M6 | MEDIUM | security | CancelMembershipApplicationAction.php:20 (+ Invite/Approve/Reject/Revoke actions)
No authorization; Cancel takes no actor at all — anyone can cancel anyone's pending application, unaudited (no cancelled_by). Inviter/reviewer/actor args are never checked for membership or capability; package ships no policies.
Recommend: document host-must-gate prominently; add actor==applicant-or-admin check + cancelled_by audit column. Confidence: high.

M7 | MEDIUM | bug/security | InviteMemberAction.php:27-32; ApplyForMembershipAction.php:29-31
Input validation gaps: email never format/length-checked ('' allowed; >255 → DB 500); justification rejects only `=== ''` (whitespace passes, unbounded text); past expiresAt accepted; meta array and reviewer notes unbounded.
Recommend: email format + max:255, trim + non-empty + max justification, reject past expiresAt, cap meta/notes sizes. Confidence: high.

M8 | MEDIUM | bug | ChangeMemberRoleAction.php:23-37
Check-then-act race can resurrect a concurrently removed member: membership read outside txn, guard evaluated on stale snapshot, then syncWithoutDetaching re-inserts inside persist's transaction.
Recommend: re-check existence under lock inside the write transaction. Confidence: med-high.

M9 | LOW-MED | bug | AddMemberAction.php:58-73
Re-add/role-change resets joined_at: pivotData always sets `joined_at: now()` and syncWithoutDetaching updates existing pivot rows.
Recommend: set joined_at on insert only. Confidence: high.

M10 | LOW-MED | performance | AddMemberAction.php:63-68
Schema `hasColumn()` probe on every membership write, inside the transaction.
Recommend: cache per-table. Confidence: high.

M11 | LOW | bug | Traits/HasMembers.php:44-66
Deleting hook mass-updates bypass model events/notifiers and set revoked_at with revoked_by=null (transitionStatus normally requires an actor); applications cancelled silently. Cross-scope wipe is documented/intended.
Recommend: document event bypass; consider setting revoked_by. Confidence: high.

M12 | LOW | security | Models/MembershipApplication.php:38-50 (also moderation Block.php:48-54)
Overly broad $fillable (status, granted_role, reviewer_*, reviewed_at, cancelled_at). Package writes are explicit, but host mass-assignment could escalate lifecycle state. owner_* correctly excluded.
Recommend: narrow fillable + forceFill internally. Confidence: med.

M13 | MEDIUM | security | InviteMemberAction + ApplyForMembershipAction (general)
No abuse controls: unique indexes dedup identical keys only; varying emails/justifications create unbounded rows, each invite emitting a mail-driving event (mail-bomb/row-spam vector for exposed endpoints).
Recommend: document host rate-limiting; consider per-subject quotas. Confidence: med.

MODERATION

D1 | MEDIUM | bug | Traits/HasBlocks.php:64-83 vs Contracts/BlocksEntity.php:19, Actions/BlockEntityAction.php:24
Type mismatch: block() accepts ?CarbonInterface and forwards to execute(?CarbonImmutable). A mutable Carbon date → TypeError; also a PHPStan L6 violation.
Evidence: `?CarbonInterface $expiresAt = null` … `expiresAt: $expiresAt` into `?CarbonImmutable $expiresAt`.
Recommend: type as CarbonImmutable or convert via CarbonImmutable::createFromInterface. Confidence: high.

D2 | MEDIUM | bug | Traits/HasBlocks.php:79 + Actions/BlockEntityAction.php:27
Invalid reason strings silently coerced to Other: `BlockReason::tryFrom($reason)` null → `??= Other`. Typos misrecord moderation history.
Recommend: throw InvalidArgumentException on unknown reason. Confidence: high.

D3 | MEDIUM | security | Traits/HasBlocks.php:85-99; Traits/HasModerationActions.php:46-60
Arbitrary model-class instantiation from string: `is_a($type, Model::class, true)` + `(new $type)->newQuery()->find($id)`. Safe when host hardcodes class (as docs show), but hosts forwarding request input get a model-probing/IDOR gadget.
Recommend: restrict to morph-map allowlist / expected actor classes. Confidence: med.

D4 | LOW-MED | bug | migration 000002:22; Models/ModerationAction.php:39-43; Contracts/RecordsModerationAction.php:13-19
notes column unreachable via public API: schema + fillable have it, but neither the contract, action, nor trait accepts/passes notes. Schema/model/API drift.
Recommend: add ?string $notes through the chain or drop the column. Confidence: high.

D5 | LOW-MED | bug | Models/Block.php:52 (+ migration 000001:24)
lifted_by_type/id never written anywhere in src; transitionTo(Lifted) sets lifted_at only. Lift audit trail permanently null.
Recommend: accept actor in lift path or remove columns. Confidence: high.

D6 | MEDIUM | performance | Traits/HasBlocks.php:20-30
Deleting hook loads ALL active blocks (get()->each) and saves one-by-one with no transaction: N+1 UPDATEs + unbounded memory on heavily-blocked models.
Recommend: chunk + mass update inside a transaction (when events unneeded). Confidence: high.

D7 | LOW | bug | Traits/HasBlocks.php:20-30
Deleting hook is owner-scope-bound, so other-scope active blocks survive pointing at a deleted model — inconsistent with HasMembers' deliberate cross-scope cleanup.
Recommend: withoutGlobalScope(OwnerScope::class) like HasMembers, or document. Confidence: med-high.

D8 | LOW-MED | performance | Actions/ExpireModerationBlocksAction.php:32-47
One UPDATE per block in chunk loop. Correct (model events fire) but slow at scale.
Recommend: optional bulk mass-update path. Confidence: high.

D9 | LOW | bug | Actions/BlockEntityAction.php:38-54
Duplicate active blocks allowed — always inserts, no dedup/unique index. isBlocked() stays correct; table accumulates dupes.
Recommend: idempotent re-block (extend expiry) or partial unique index. Confidence: high.

D10 | LOW | bug | Actions/RecordModerationAction.php:17-23
Empty reason allowed; reason/metadata unbounded.
Recommend: non-empty + length caps. Confidence: high.

POSITIVES (brief): owner scoping consistent (HasOwner on all 4 models, OwnerWriteGuard on writes, OwnerBatchRunner in expire command, MembershipSubjectGuard); UUID PKs; no FK constraints/cascades; invitation tokens 64-char random, sha256 at rest, hidden, hash_equals, single-use terminal transitions with row locks; invite/apply race-safe (txn + lockForUpdate + unique index + QueryException fallback); events dispatched post-commit; no XSS/injection/SSRF/traversal/deserialization surface (no routes/controllers/views, no DB::raw, no unserialize, no file/network I/O); migrations well indexed; stateless singletons, no static mutable state, no cache (Octane-safe, no stampede); solid Pest coverage incl. owner-isolation tests; no SoftDeletes.

### Prior-audit chunk: membership

### Prior-audit section
### membership
Bugs:
- `Models/MembershipApplication.php:38-50` HIGH — `status/granted_role/reviewer_*/reviewed_at/cancelled_at` fillable, no model default; direct `create()` bypasses Approve/Reject.
- `Actions/InviteMemberAction.php:34-45` MEDIUM — existing Pending returned as-is, `$token` stays null → no resend.
- DONE (2026-09-13, §8 item 1) — Missing composite unique `(subject,email,role,status)` MEDIUM — only `token` unique; `lockForUpdate` dedupe races. Fixed: composites folded into the applications/invitations creates; lock + 23000 rescue returns existing.
Security: clean — `OwnerWriteGuard` + `lockForUpdate` on Accept/Revoke/Cancel/Approve/Reject; `hash_equals` token; `token` hidden.
Performance: `AddMemberAction.php:65` LOW — `hasColumn` schema check inside txn per call (cache it).

### Prior-audit fix-first rows
| — | membership | `Models/MembershipApplication.php:38-50` | `status/granted_role/reviewer_*` fillable bypasses Approve/Reject | HIGH |

### Migration-batch rows (§8, code may already be fixed)
| 1 | membership missing composite unique (§1) | Composites folded into the applications/invitations creates; lock + 23000 rescue returns existing | `packages/membership/docs/04-usage.md` |

#### Verdicts: membership

- [R2:M1] CONFIRMED HIGH bug | packages/membership/src/Actions/InviteMemberAction.php:39-45 | Expired-Pending returned as-is, no event; expireIfDue uncalled in src; no expire command; docs 04-usage.md:129-133 promise terminal
- [R2:M2] CONFIRMED MEDIUM security | packages/membership/src/Actions/AcceptInvitationAction.php:21-44 | No token param, email match only; matchesToken zero src callers; safety = host token-hash lookup per docs 04-usage.md:98-102
- [R2:M3] CONFIRMED MEDIUM bug | packages/membership/src/Actions/ApproveMembershipApplicationAction.php:49-70 | Approve fires onMemberAdded 2x (via AddMemberAction:93 + direct :64); role change fires add + onMemberRoleChanged
- [R2:M4] CONFIRMED MEDIUM bug | packages/membership/src/Actions/ApproveMembershipApplicationAction.php:49-53 | applicant_id nullable (migration 000001:22), no FK cascade; null subject/applicant hits Model typehints in AddMemberAction
- [R2:M5] CONFIRMED MEDIUM bug | packages/membership/src/Services/MembershipRoleSyncService.php:118-124 | removeRole unguarded (no hasRole/catch); vendor collectRoles->getStoredRole throws RoleDoesNotExist, rolls back caller txn
- [R2:M6] CONFIRMED MEDIUM security | packages/membership/src/Actions/CancelMembershipApplicationAction.php:20 | Cancel takes no actor, no cancelled_by; no Gate/policy/authorize in src; inviter/reviewer never capability-checked
- [R2:M7] CONFIRMED MEDIUM bug/security | packages/membership/src/Actions/InviteMemberAction.php:27-32 | No email format/max, '' allowed; justification ==='' only; past expiresAt ok; meta/reviewer notes unbounded
- [R2:M8] CONFIRMED MEDIUM bug | packages/membership/src/Actions/ChangeMemberRoleAction.php:23-37 | Membership read outside txn (:24), guard on stale snapshot, syncWithoutDetaching re-inserts in persist txn: resurrect race
- [R2:M9] CONFIRMED LOW-MED bug | packages/membership/src/Actions/AddMemberAction.php:58-73 | pivotData always joined_at=now; syncWithoutDetaching updates existing pivot rows, resetting join date on re-add/role change
- [R2:M10] DOWNGRADED (was LOW-MED) LOW performance | packages/membership/src/Actions/AddMemberAction.php:63-68 | DUP AUD:B4. hasColumn probe inside txn per insert-path write; adopt audit LOW
- [R2:M11] CONFIRMED LOW bug | packages/membership/src/Traits/HasMembers.php:44-66 | Deleting hook mass-updates bypass events; sets revoked_at with revoked_by=null though transitionStatus requires actor
- [R2:M12] CONFIRMED HIGH security | packages/membership/src/Models/MembershipApplication.php:38-50 | DUP AUD:B1. status/granted_role/reviewer_* fillable; direct create() bypasses Approve/Reject; adopt audit HIGH
- [R2:M13] CONFIRMED MEDIUM security | packages/membership/src/Actions/InviteMemberAction.php:27 | No throttle/rate-limit/quota in src; unique index dedups identical keys only; varying emails spam rows + mail events
- [AUD:B1] ADOPTED HIGH bug | packages/membership/src/Models/MembershipApplication.php:38-50 | Fillable lifecycle fields bypass Approve/Reject via direct create(); no model default/guard; primary, see AUD:Q#1 R2:M12
- [AUD:B2] ADOPTED MEDIUM bug | packages/membership/src/Actions/InviteMemberAction.php:34-45 | DUP R2:M1. Existing Pending returned as-is, token null -> no resend event; same return-existing path as expiry deadlock
- [AUD:B3] FIXED MEDIUM bug | packages/membership/database/migrations/2000_01_01_000001_create_membership_applications_table.php:37-40 | Composite uniques present (apps :37-40, invites :38-41) + lockForUpdate + 23000 rescue; 2026-09-13 fix verified
- [AUD:B4] ADOPTED LOW performance | packages/membership/src/Actions/AddMemberAction.php:65 | DUP R2:M10. hasColumn schema probe inside txn per call; cache per table
- [AUD:Q#1] ADOPTED HIGH bug | packages/membership/src/Models/MembershipApplication.php:38-50 | DUP AUD:B1. Fix-first row: fillable status/granted_role/reviewer_* bypasses Approve/Reject
- [AUD:Q#2] FIXED MEDIUM bug | packages/membership/database/migrations/2000_01_01_000002_create_membership_invitations_table.php:38-41 | DUP AUD:B3. Migration-batch row: composites + lock + 23000 rescue verified in current source

### Prior-audit chunk: moderation

### Prior-audit section
### moderation
Bugs:
- `Models/Block.php:48-54` MEDIUM — `status/lifted_*/expires_at` fillable bypasses `transitionTo()`.
- `Block.php:121-142` MEDIUM — `Active` keeps stale `expires_at`; `Lifted` never sets actor.
- Scope gap MEDIUM — past-due Active in neither `scopeActive` nor `scopeExpired` until sweeper runs.
- `BlockEntityAction.php:38-54` MEDIUM — no duplicate-Active guard; double-click stacks blocks.
Security: `validateOwnerScopedModel` skips non-`OwnerScopeConfigurable` LOW (was MEDIUM) — by-design per-tenant block of shared identity.
Performance: `ExpireModerationBlocksAction:32-47` LOW (was MEDIUM) — `chunkById(100)` + per-block `expire()->save()` preserves events; keep unless proven hot.

---

#### Verdicts: moderation

- [R2:D1] CONFIRMED MEDIUM bug | packages/moderation/src/Traits/HasBlocks.php:64-83 | block() takes ?CarbonInterface, forwards to execute(?CarbonImmutable) with no conversion -> TypeError on mutable Carbon
- [R2:D2] CONFIRMED MEDIUM bug | packages/moderation/src/Traits/HasBlocks.php:79 | tryFrom($reason) null passes null, BlockEntityAction:27 ??= Other; unknown reason strings silently misrecorded
- [R2:D3] CONFIRMED MEDIUM security | packages/moderation/src/Traits/HasBlocks.php:85-99 | is_a+new $type->find($id) in both traits; OwnerWriteGuard only for owner-scoped types; request-driven type = IDOR probe
- [R2:D4] CONFIRMED LOW-MED bug | packages/moderation/src/Contracts/RecordsModerationAction.php:13-19 | notes in schema (migration 000002:22)+fillable but no $notes param in contract/action/trait; column unreachable
- [R2:D5] CONFIRMED MEDIUM bug | packages/moderation/src/Models/Block.php:121-142 | DUP AUD:B2 (partial). transitionTo(Lifted) sets lifted_at only; lifted_by_* never written in src; adopt audit MEDIUM
- [R2:D6] CONFIRMED MEDIUM performance | packages/moderation/src/Traits/HasBlocks.php:20-30 | Deleting hook get()->each + per-block expire()->save(), no txn: N+1 UPDATEs + unbounded memory
- [R2:D7] CONFIRMED LOW bug | packages/moderation/src/Traits/HasBlocks.php:20-30 | Deleting hook uses scoped blocks() (no withoutGlobalScope); other-scope active blocks survive on deleted model
- [R2:D8] DOWNGRADED (was LOW-MED) LOW performance | packages/moderation/src/Actions/ExpireModerationBlocksAction.php:32-47 | DUP AUD:B6. chunkById + per-block expire()->save(); correct (events fire), slow at scale; adopt audit LOW
- [R2:D9] CONFIRMED MEDIUM bug | packages/moderation/src/Actions/BlockEntityAction.php:38-54 | DUP AUD:B4. Always inserts, no dedup guard/unique index; double-click stacks blocks; adopt audit MEDIUM
- [R2:D10] CONFIRMED LOW bug | packages/moderation/src/Actions/RecordModerationAction.php:17-23 | No reason validation: '' allowed, no length caps (reason string(255) -> DB 500 on overflow); metadata unbounded
- [AUD:B1] ADOPTED MEDIUM bug | packages/moderation/src/Models/Block.php:48-54 | status/lifted_*/expires_at fillable; direct create/update bypasses transitionTo(); location exists, claim plausible
- [AUD:B2] ADOPTED MEDIUM bug | packages/moderation/src/Models/Block.php:121-142 | Active keeps stale expires_at (not cleared :135-137); Lifted never sets actor; primary for lifted-actor, see R2:D5
- [AUD:B3] ADOPTED MEDIUM bug | packages/moderation/src/Models/Block.php:102-119 | Past-due Active in neither scopeActive (excludes expired :106-109) nor scopeExpired (status=Expired only :118)
- [AUD:B4] ADOPTED MEDIUM bug | packages/moderation/src/Actions/BlockEntityAction.php:38-54 | DUP R2:D9. No duplicate-Active guard; double-click stacks blocks
- [AUD:B5] ADOPTED LOW security | packages/moderation/src/Actions/BlockEntityAction.php:57-72 | validateOwnerScopedModel skips non-OwnerScopeConfigurable (:67-69); by-design per-tenant block of shared identity
- [AUD:B6] ADOPTED LOW performance | packages/moderation/src/Actions/ExpireModerationBlocksAction.php:32-47 | DUP R2:D8. chunkById(100)+per-block save preserves events; keep unless proven hot

---

## Round 2 — `seating`, `orders`, `filament-events`, `filament-inventory`

### E2E findings (verbatim)

E2E review: seating, orders, filament-events, filament-inventory. Repo-rule compliance is good overall (uuid PKs, no FK constraints — foreignUuid without constrained(), int minor-unit money, no SoftDeletes, navigation.group + getNavigationGroup everywhere, Actions for orchestration). Findings below; positives at end.

## SEATING

1) HIGH / bug — `packages/seating/src/Actions/EnsureSeatHoldAction.php:136-147` (+ `Services/DefaultSeatAllocator.php:148-159` same pattern). Concurrent double-hold race. Availability is decided by `SELECT … lockForUpdate` on seats + `whereDoesntHave('holds', expires_at > now)`, then holds are bulk-inserted. Under MySQL REPEATABLE READ (default) a second txn blocked on the row lock still uses its start-of-txn snapshot after unblocking, so it re-selects the same seats; unlike allocations (partial unique index on pgsql/sqlite, `2000_01_01_000005` migration), `seat_holds` has no DB guard on active holds. Evidence: `availableSeatsQuery()` + `SeatHold::query()->insert($rows)`. Recommendation: add a conditional unique guard for unconverted, unexpired holds per seat (or lock + re-check holds after acquiring seat locks with `lockForUpdate` on the holds subquery / SELECT … FOR UPDATE on existing hold rows + unique key), and document required isolation level. Confidence: med (isolation-dependent).

2) MEDIUM / performance — `packages/seating/src/Services/SeatLayoutRenderer.php:19-49`. N+1: one seats query + one `max(column_number)` query per section. Recommendation: eager-load sections with seats once, compute bounds in memory. Confidence: high.

3) MEDIUM / performance — `packages/seating/src/Livewire/SeatMap.php:101-154`. `getLayoutProperty` + `getStatusProperty` load the entire venue (all sections/seats/holds/allocations) on every Livewire round-trip; `toggleSeat` re-resolves the map each click; `$picked` is unbounded. Large venues = multi-MB Livewire payloads + full-table scans per click. Recommendation: paginate/virtualize by section, cache layout per map version, cap selection size. Confidence: high.

4) MEDIUM / bug — `packages/seating/src/Actions/ConvertHoldsToAllocationsAction.php:62-72` and `EnsureSectionAllocationAction.php:37-44`. Converted allocations never copy the source hold's/section's owner; owner comes purely from ambient `OwnerContext` via HasOwner auto-assign. Cross-owner batch conversion misattributes ownership. (Orders models copy parent owner in `creating` hooks; seating has no equivalent.) Recommendation: set owner from `$lockedHold`/parent explicitly. Confidence: med.

5) MEDIUM / bug — `packages/seating/src/Actions/EnsureSeatHoldAction.php:116-122` (same in `DefaultSeatAllocator.php:130-136`). `SeatHold::query()->insert($rows)` bypasses HasOwner `creating`/`saving` guards (explicit-global assertion, owner/context match). With unresolved context, holds are silently created global. Recommendation: assert `OwnerContext::resolve() !== null || isExplicitGlobal()` before insert (HasOwner does this for `create()`). Confidence: high.

6) LOW / bug — `packages/seating/src/Console/Commands/ReleaseExpiredHoldsCommand.php:22,41-47`. `--chunk` unvalidated: 0/negative breaks `chunkById`, huge values OOM via `$holds->each->delete()`. Recommendation: clamp to a sane range. Confidence: high.

7) LOW / performance — `SeatMap.php:41-46`, `SeatSection.php:40-46`, `Seat.php:45-51`. Cascading deletes via `->each(fn => ->delete())` = N queries, no chunking. Recommendation: chunk deletes. Confidence: high.

## ORDERS

8) HIGH / bug (financial) — `packages/orders/src/Transitions/PaymentConfirmed.php:38-98`, passthrough `Actions/RegisterOrderPayment.php:18-30`. No amount validation: a 0/negative-amount "payment" creates a Completed payment, transitions to Processing and sets `paid_at` (the `created` hook only guards `paid_total` increments, not the transition). No balance-due/overpayment check either — inconsistent with `RefundProcessed` which validates `amount > 0`. Recommendation: require `amount > 0` (and optionally `<= balance due`) before recording. Confidence: high.

9) MEDIUM / bug — `packages/orders/src/Actions/CreateOrder.php:99-114,240-257`. Caller-supplied order totals trusted verbatim (grand_total can be 0 with items); `addItem` uses `$itemData['name']` (missing key = Error, not validation) and accepts negative quantity/unit_price/discount/tax. Recommendation: validate items (name required, qty >= 1, amounts >= 0) and recompute/verify totals server-side. Confidence: high.

10) MEDIUM / bug — `packages/orders/src/Models/Order.php:398-408`. `recalculateTotals()` ignores per-item `discount_amount` (uses order-level `discount_total` only), so item discounts silently vanish from grand_total. Recommendation: subtract `sum(discount_amount)` or document that item discounts are unsupported. Confidence: high.

11) MEDIUM / security — `packages/orders/src/Policies/OrderPolicy.php:15-69`. Owner-blind: view/update/delete/cancel/refund check only `$user->can(...)`, while `Policies/Concerns/HandlesOrderRelationAuthorization.php` enforces owner checks for relations. Any holder of `view_order` can access any owner's orders wherever the policy is the enforcement point. Recommendation: mirror the relation-trait owner checks in OrderPolicy. Confidence: med (depends on call-site enforcement; Filament layer may scope separately).

12) MEDIUM / bug — `packages/orders/database/migrations/2000_11_01_000001_create_orders_table.php:57-60` + `Actions/CreateOrder.php:333-340`. Intake dedup unique key `(owner_type, owner_id, intake_source, intake_id)` contains nullable columns; on MySQL NULLs are distinct, so global-owner (or any NULL) duplicates bypass the DB constraint and concurrent same-intake creates both pass the app-level `findExistingIntake` check. Recommendation: require non-null intake pair scoping or serialize on intake key (e.g. locked sentinel row / cache lock). Confidence: med.

13) LOW / bug — `packages/orders/src/Actions/GenerateInvoice.php:64-88`. Fresh random invoice number minted on every download, never persisted — duplicate/conflicting invoice numbers, accounting-hostile (receipt correctly reuses order number). Recommendation: persist invoice number per order. Confidence: high.

14) LOW / bug — `packages/orders/src/States/OrderStatus.php:153-157` defaults new orders to Processing while the migration defaults `status` to 'created' and the lifecycle starts Created→PendingPayment. Direct `Order::create()` without status skips the payment flow. Recommendation: align default to Created. Confidence: med.

## FILAMENT-EVENTS

15) HIGH / bug — Record lifecycle actions placed in table `headerActions` (no record context), so every click fails: `Resources/EventResource.php:126-160` (publish/archive/cancel), `Resources/EventOccurrenceResource.php:103-153` (delay/postpone/cancel/complete), `Resources/EventSessionResource.php:119-172` (same four). Closures type-hint non-nullable `Event $record` etc., but header actions receive no record → TypeError. Corroborated in-repo: clone actions correctly live in `->actions([...])`, and Attendance/Venue/Registration resources use header actions only for Import/Export. Recommendation: move all record actions to `actions()`. Confidence: high (framework contract + in-repo counter-examples).

16) HIGH / security — Importers accept cross-owner foreign IDs with zero revalidation, violating the package's own guardrail: `Actions/Importer/EventSessionImporter.php:19-49` (`event_id`, `event_occurrence_id`, no rules) and `Actions/Importer/EventRegistrationImporter.php:27-68` (`event_id`, `event_occurrence_id`). An import can attach sessions/registrations to another owner's events; records then auto-assign ambient owner. Recommendation: `OwnerWriteGuard::findOrFailForOwner(Event::class, …)` in `beforeCreate`/`beforeSave` (as `CreateEventOccurrence`/`CreateEventSession` pages already do). Confidence: high.

17) MEDIUM / security — `Actions/Importer/VenueImporter.php:30-49`. `address_id` resolved via unscoped `Address::query()->findOrFail($state)` then attached to the venue — cross-context address attach. Recommendation: scope the lookup to the current owner context. Confidence: med (Address owner config not verified here).

18) MEDIUM / bug — `Resources/EventResource/Pages/CreateEvent.php:21-24` (identical in EditEvent, ListEvents, ViewEvent and all four EventTemplate pages): `boot()` calls `OwnerContext::setForRequest(null)`, forcing explicit-global for the entire request and wiping any resolver-provided owner. With `events.owner.enabled=true` (default), the event admin becomes global-only and newly created events are global instead of owned. Recommendation: remove; let the resolver supply context (or scope explicitly per query). Confidence: high on mechanism, med on production impact (depends on resolver binding).

19) MEDIUM / security+correctness — `Pages/ApprovalQueue.php:73-129`. approve/reject/assign perform direct `$record->update(...)` with no authorization check (any page viewer can approve) and bypass any domain workflow on `EventSubmission` (no status transition/audit). Recommendation: gate with policy/permission + drive approval through the events-domain workflow. Confidence: med-high.

20) LOW / bug — `Resources/EventResource.php:221`. Slug `unique(ignoreRecord: true)` is global across owners → slug collisions deny creation for other tenants. Recommendation: scope uniqueness per owner. Confidence: med.

21) LOW / performance — `Pages/CheckInConsole.php:103-110`. Leading-wildcard `LIKE %…%` on `pass_no`/`registration_no` (unindexed scan) with uninterpolated user input, so `%`/`_` act as wildcards. Recommendation: escape LIKE specials. Confidence: high.

Note (not findings): Venue, VenueSpace, EventTaxonomy, EventTerm models verified to have no HasOwner (global vocabularies/venues), so their resources' lack of `getEloquentQuery` scoping is consistent. Exporters ride the scoped table query. `EventChangeLogResource ->html(JsonDisplay::format())` is escaped (`e()` in JsonDisplay) — no XSS.

## FILAMENT-INVENTORY

22) HIGH / bug — `Actions/CycleCountAction.php:66-69,102`. `system_quantity` is a `disabled()` display field, but the submit handler reads `$data['system_quantity']` — disabled fields are not dehydrated, so this is an undefined-key error on submit. It also trusts a client-submitted system quantity for the variance note. Recommendation: `->dehydrated()` or (better) recompute system quantity server-side in the action. Confidence: med (framework behavior; verify by running the action).

23) LOW / performance — Navigation badges fire COUNT queries on every admin request: `InventoryLevelResource.php:78-86` (plus `whereRaw`), `InventoryLocationResource.php:116-121`, `InventoryAllocationResource.php:74-81`, `InventoryBatchResource.php:118-123`. Recommendation: cache briefly or drop badges. Confidence: high.

24) LOW / bug — `Widgets/ExpiringBatchesWidget.php:30-37`, `Widgets/BackordersWidget.php:30-36`, `Widgets/ReorderSuggestionsWidget.php:31-37` bake `->limit(10)` into the Filament table query, corrupting pagination totals. Recommendation: use default page size instead of limit. Confidence: med.

25) LOW / performance — `Services/InventoryStatsAggregator.php:106-122`. `Cache::remember` with 60s TTL has no stampede protection; concurrent misses all recompute. Impact is low (short TTL, cheap reports). Recommendation: `Cache::flexible()`/lock if it ever shows up in traces. Confidence: low-med.

## POSITIVES (brief)
- Owner scoping done right in most places: orders child models copy parent owner + `OwnerWriteGuard` in `creating` hooks; `AssertsOrderOwnerBoundary` on mutations; `OrderProcessingCheck` fails safe without context; filament-inventory consistently applies `InventoryOwnerScope` in resources, widgets, per-action location revalidation, and owner-suffixed cache keys; inventory domain reports/ValuationService/KPI services all scope internally (verified).
- Money integrity: paid/refunded/pending totals synced via atomic increments in model hooks; idempotent payment/refund identities backed by unique DB indexes + race handling; `RefundAllocationValidator` enforces allocation sums.
- No XSS sinks found: invoice/notification blades use escaped output; `JsonDisplay` escapes; no `{!!`, `HtmlString`, `eval`, deserialization, SSRF, or path-traversal sinks in the four packages (GenerateInvoice `save($path)` takes caller paths only).
- No Octane-unsafe mutable static state (only config-key declarations); `OwnerContext` uses request attributes + finally-restored fallback.
- Migrations well indexed (seat lookup composites, order status/customer composites, payment/refund gateway indexes); no FK violations of the no-FK rule.
- Test coverage exists for all four areas (notably orders lifecycle/refunds/intake, seating allocator/conversion/expiry, FilamentEvents importer/approval/page surfaces, FilamentInventory aggregator/scope/plugin).

### Prior-audit chunk: seating

### Prior-audit section
### seating
Bugs:
- DONE (2026-09-13, §8 item 5) — `ConvertHoldsToAllocationsAction` HIGH — no lock, no owner comparison, check-then-create races → double allocation. Fixed: `FOR UPDATE` locks + partial unique `(seat_id)` where active (§8); concurrent converts skip instead of double-allocating.
- `DefaultSeatAllocator:100-136`, `EnsureSeatHoldAction:86-122` MEDIUM-HIGH (was HIGH) — bulk `insert()` bypasses events/validation; owner manually assigned so not cross-tenant, but `seat_id` TOCTOU stands.
- `SeatHold/SeatAllocation` no `booted()` HIGH — any `seat_id/held_by_*/allocated_to_*` accepted outside allocator.
- `Seat/SeatMap/SeatSection` `each(delete)` no txn/chunk MEDIUM.
Security:
- `Livewire/SeatMap:48-93,161-177` MEDIUM (was HIGH) — no `authorize`/rate-limit; `seatable_type` public prop into `where()` with no allowlist. Enumeration, not takeover.
- Filament holds/allocations via managers may bypass `OwnerUiScope` MEDIUM — only `SeatMapResource` scoped.
Performance:
- `SeatMap getStatusProperty:112-154` HIGH — loads entire venue per render; 10k seats OOM. Page sections or cached status query.
- Allocator double query + gap locks MEDIUM — single query + `ORDER BY FIELD` + `SKIP LOCKED`.

### Prior-audit fix-first rows
| 20 | seating | `Actions/ConvertHoldsToAllocationsAction` | No owner validation, no `lockForUpdate` → double allocation | HIGH |
| — | seating | `SeatHold/SeatAllocation` (no `booted()`) | Any `seat_id/held_by_*/allocated_to_*` accepted outside allocator | HIGH |

### Migration-batch rows (§8, code may already be fixed)
| 5 | seating active-allocation partial unique (§2) | Kept additive (`2026_09_12_162834_*`, pgsql/sqlite; row locks elsewhere); locks + 23000-skip | `packages/seating/docs/99-troubleshooting.md` |

#### Verdicts: seating

- [R2:#1] CONFIRMED HIGH bug | Actions/EnsureSeatHoldAction.php:136-147 | lockForUpdate+whereDoesntHave+bulk insert; seat_holds migration has no active-hold unique guard, RR snapshot re-selects
- [R2:#2] CONFIRMED MEDIUM perf | Services/SeatLayoutRenderer.php:19-49 | per-section seats query :22 plus max(column) query :46; bounds computable from loaded seats
- [R2:#3] CONFIRMED HIGH perf | Livewire/SeatMap.php:101-154 | DUP AUD:B7, adopt HIGH; layout+status load full venue per render, $picked unbounded :30,90
- [R2:#4] CONFIRMED MEDIUM bug | Actions/ConvertHoldsToAllocationsAction.php:61-72 | allocation create copies no hold owner; owner purely ambient via HasOwner; same in EnsureSectionAllocationAction:37-44
- [R2:#5] CONFIRMED MEDIUM bug | Actions/EnsureSeatHoldAction.php:109-122 | bulk insert() bypasses HasOwner creating guards; null context silently creates global holds; same in DefaultSeatAllocator:123-136
- [R2:#6] CONFIRMED LOW bug | Console/Commands/ReleaseExpiredHoldsCommand.php:22-47 | --chunk cast to int unclamped; 0/neg breaks chunkById, huge OOMs via each->delete
- [R2:#7] CONFIRMED MEDIUM perf | Models/SeatMap.php:44 | DUP AUD:B4, adopt MEDIUM; cascading each(delete) in Map:44 Section:43-44 Seat:48-49, no chunk/txn
- [AUD:B1] FIXED HIGH bug | Actions/ConvertHoldsToAllocationsAction.php:34-79 | DONE §8 item5 verified: FOR UPDATE locks on hold+seat, active-exists skip, 23000-skip
- [AUD:B2] ADOPTED MEDIUM-HIGH bug | Services/DefaultSeatAllocator.php:130-136 | DUP R2:#1+R2:#5 partial; bulk insert+manual owner+seat TOCTOU all stand
- [AUD:B3] ADOPTED HIGH bug | Models/SeatHold.php | no booted/creating/saving guards in SeatHold or SeatAllocation; arbitrary seat_id/held_by accepted
- [AUD:B4] ADOPTED MEDIUM perf | Models/Seat.php:48-49 | DUP R2:#7; each(delete) cascades across Map/Section/Seat
- [AUD:B5] ADOPTED MEDIUM sec | Livewire/SeatMap.php:48-93 | toggleSeat has no authorize/rate-limit; seatable_type public prop into where() :167-170 unallowlisted
- [AUD:B6] UNVERIFIED MEDIUM sec | packages/seating/src | no Resource/Manager/OwnerUiScope under seating src; need runtime check locating the Filament UI managing holds/allocations
- [AUD:B7] ADOPTED HIGH perf | Livewire/SeatMap.php:112-154 | DUP R2:#3; getStatusProperty eager-loads whole venue per render
- [AUD:B8] ADOPTED MEDIUM perf | Services/DefaultSeatAllocator.php:71-93 | preferred+fallback = two locked availability queries; no ORDER BY FIELD / SKIP LOCKED
- [AUD:Q#1] FIXED HIGH bug | Actions/ConvertHoldsToAllocationsAction.php:34-79 | DUP AUD:B1; fix-first row 20 verified fixed
- [AUD:Q#2] ADOPTED HIGH bug | Models/SeatAllocation.php | DUP AUD:B3; fix-first no-booted row stands
- [AUD:Q#3] FIXED n/a bug | migrations/2026_09_12_162834_* | DUP AUD:B1; §8 row5 partial unique (pgsql/sqlite) + lock/skip verified in code

### Prior-audit chunk: orders

### Prior-audit section
### orders
Bugs:
- DONE (2026-09-13, §8 item 10) — `Order:399-409` HIGH — `recalculateTotals` tax-inconsistent (`subtotal=sum(tax-inclusive total)`, keeps `tax_total` separate, `grand=items+shipping-discount` drops tax); disagrees with `CreateOrderFromCart:40-50` by `tax_total`. Fixed: ex-tax subtotal, `grand = subtotal + tax_total + shipping - discount`.
- DONE (2026-09-13, §8 item 10) — `Order:380-384` HIGH — `getBalanceDue = grand-paid+refunded` (10000/10000/2000 → 2000 due, should be 0). Fixed: `grand - paid`.
- `OrderPayment:222-241` MEDIUM — lock-free `exists()` TOCTOU; only `PaymentConfirmed:42-84` handles 23000.
- `CreateOrder:154-176` MEDIUM — strict `(string)===/(int)===` intake compare breaks whitespace-variant retry.
- `OrderItem saving:232-234` LOW — unconditional `total` overwrite, no clamp/quantity check.
Security:
- Mass assignment HIGH — `Order:105-134 owner_*/status/*_at`; `Payment:60-72`, `Refund:63-77`, `Item:68-87 status/*_at`.
- Child inherit when scoping disabled MEDIUM — fall back to unscoped `findOrFail` + inherit when `orders.owner.enabled` off.
- `findExistingIntake:333-340` LOW — `forOwner(includeGlobal)` oracle (conflict vs return reveals totals to guesser).
Performance:
- DONE (2026-09-13, §8 item 10) — 4–5 `sum()` per balance check HIGH — single `SUM(CASE)` or cached columns. Fixed: `paid_total` / `refunded_total` / `pending_refunded_total` folded into the orders create; `OrderPayment`/`OrderRefund` model events are the single sync mechanism (atomic increments), and the five manual mutation sites now refresh instead of assigning.
- Row-by-row inserts + `fresh` in txn MEDIUM — 50 lines = 50+ inserts + selects under lock.

### Prior-audit fix-first rows
| 11 | orders | `Models/Order.php:399-409,380-384` | `recalculateTotals` tax-inconsistent; `getBalanceDue` adds refunds back | HIGH |
| 12 | orders | mass assignment | `owner_*/status/*_at` fillable on Order/Payment/Refund/Item | HIGH |

#### Verdicts: orders

- [R2:#8] CONFIRMED HIGH bug | Transitions/PaymentConfirmed.php:38-98 | no amount>0 check: 0/neg amount yields Completed payment, Processing, paid_at; cf RefundProcessed:39-40 validates
- [R2:#9] CONFIRMED MEDIUM bug | Actions/CreateOrder.php:99-114 | caller totals trusted verbatim; addItem name key unguarded :247, qty/amounts unvalidated :249-252
- [R2:#10] CONFIRMED MEDIUM bug | Models/Order.php:398-408 | grand ignores per-item discount_amount; §8 tax fix present but item discounts still vanish
- [R2:#11] CONFIRMED MEDIUM sec | Policies/OrderPolicy.php:15-69 | permission-only checks, no owner match; relation trait enforces owner at HandlesOrderRelationAuthorization:19-33
- [R2:#12] CONFIRMED MEDIUM bug | migrations/2000_11_01_000001:57-60 | intake unique over nullable cols; MySQL NULLs distinct so global-owner dupes bypass; app check :333-340 races
- [R2:#13] CONFIRMED LOW bug | Actions/GenerateInvoice.php:64-88 | fresh random invoice number per download via documentData:80, never persisted
- [R2:#14] CONFIRMED LOW bug | States/OrderStatus.php:154-157 | default state Processing vs migration default 'created' :20; creating hook sets no status :466-471
- [AUD:B1] FIXED HIGH bug | Models/Order.php:398-408 | DONE §8 item10 verified: ex-tax subtotal, grand=subtotal+tax+ship-discount
- [AUD:B2] FIXED HIGH bug | Models/Order.php:379-382 | DONE §8 item10 verified: getBalanceDue=max(0,grand-paid)
- [AUD:B3] ADOPTED MEDIUM bug | Models/OrderPayment.php:280-293 | lock-free exists() identity pre-check stands; only PaymentConfirmed:67-84 handles 23000 race
- [AUD:B4] ADOPTED MEDIUM bug | Actions/CreateOrder.php:154-159 | strict (string)/(int)=== intake compare stands; whitespace-variant retry mismatches
- [AUD:B5] ADOPTED LOW bug | Models/OrderItem.php:232-234 | saving unconditionally overwrites total; calculateTotal:174-180 has no clamp or quantity check
- [AUD:B6] ADOPTED HIGH sec | Models/Order.php:107-134 | owner_*/status/*_at fillable stands on Order/Payment:60-72/Refund:63-77/Item:68-87
- [AUD:B7] ADOPTED MEDIUM sec | Models/OrderItem.php:213-214 | scope-disabled path uses unscoped Order findOrFail then inherits parent owner; same in Payment:205/Refund:205
- [AUD:B8] ADOPTED LOW sec | Actions/CreateOrder.php:333-340 | forOwner(includeGlobal) intake lookup stands; conflict-vs-return oracle plausible
- [AUD:B9] FIXED HIGH perf | Models/Order.php:354-367 | DONE §8 item10 verified: cached paid/refunded/pending cols, atomic increments in created/updated hooks
- [AUD:B10] ADOPTED MEDIUM perf | Actions/CreateOrder.php:129-147 | row-by-row addItem loop plus fresh(items,addresses) inside txn stands
- [AUD:Q#1] FIXED HIGH bug | Models/Order.php:379-408 | DUP AUD:B1+AUD:B2; fix-first row 11 verified fixed
- [AUD:Q#2] ADOPTED HIGH sec | Models/Order.php:107-134 | DUP AUD:B6; fix-first row 12 stands

### Prior-audit chunk: filament-events

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-events` MEDIUM-verify — `EventRegistrationParticipant:50-56` only `whereHas(event)` + unguarded badge; Venue/Space/Taxonomy/Term no query (confirm global vs owner, else HIGH).
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-events

- [R2:#15] CONFIRMED HIGH bug | Resources/EventResource.php:126-160 | publish/archive/cancel in headerActions with non-nullable Event $record => TypeError; Occurrence:103/Session:119 blocks present, clone in actions()
- [R2:#16] CONFIRMED HIGH sec | Actions/Importer/EventSessionImporter.php:19-49 | event_id/occurrence_id mapped with no rules and no resolveRecord scoping; same in EventRegistrationImporter:30-34
- [R2:#17] CONFIRMED MEDIUM sec | Actions/Importer/VenueImporter.php:46-48 | address attach via unscoped Address::findOrFail; Address owner config unverified here
- [R2:#18] CONFIRMED MEDIUM bug | Resources/EventResource/Pages/CreateEvent.php:21-24 | boot() setForRequest(null) wipes resolver owner for the request; global-only event admin
- [R2:#19] CONFIRMED MEDIUM sec | Pages/ApprovalQueue.php:73-129 | approve/reject/assign do direct $record->update with no authorize/can check and no domain workflow
- [R2:#20] CONFIRMED LOW bug | Resources/EventResource.php:221 | slug unique(ignoreRecord:true) global across owners; same pattern Occurrence:222 Session:245
- [R2:#21] CONFIRMED LOW perf | Pages/CheckInConsole.php:103-110 | leading-% LIKE on pass_no/registration_no with uninterpolated-escaped input; %/_ act as wildcards
- [AUD:B1] ADOPTED MEDIUM sec | Resources/EventRegistrationParticipantResource.php:44-56 | whereHas(event)-only scope :55 plus uncached badge :46; global-vs-owner still unconfirmed
- [AUD:B2] ADOPTED PASS info | (package-wide) | G1 navigation PASS: no static $navigationGroup; getNavigationGroup used
- [AUD:B3] ADOPTED MEDIUM perf | Resources/EventRegistrationParticipantResource.php:44-47 | G2 uncached COUNT navigation badge stands
- [AUD:B4] DOWNGRADED (was MEDIUM) LOW perf | Resources/EventSessionResource.php:64 | G3: 4 spot resources eager-load relation cols (Session/Occurrence/Registration/Participant); residual N+1 unverified
- [AUD:B5] ADOPTED MEDIUM perf | Pages/CheckInConsole.php:135-141 | G4 unpaginated scoped pluck for event select stands
- [AUD:B6] ADOPTED LOW/MEDIUM perf | (global note) | G5 domain leakage cites no filament-events instance; global note only
- [AUD:B7] ADOPTED MEDIUM perf | (global note) | G6 collection sums cites no filament-events instance; global note only

### Prior-audit chunk: filament-inventory

### Prior-audit filament notes (§6; G1-G6 global + package bullets)
- `filament-inventory` — resources `with()+OwnerScope` GOOD (only package consistent). Infolist per-row aggregates + action `location_id` scope unverified (tables/forms scoped, confirm actions).
- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

#### Verdicts: filament-inventory

- [R2:#22] CONFIRMED HIGH bug | Actions/CycleCountAction.php:66-102 | disabled() system_quantity :66-69 read from $data :102; disabled fields are not dehydrated => undefined key (runtime click firms it)
- [R2:#23] CONFIRMED MEDIUM perf | Resources/InventoryLevelResource.php:78-86 | DUP AUD:B3, adopt MEDIUM; uncached COUNT badges on Level/Location:116-121/Allocation:74-81/Batch:118-123
- [R2:#24] CONFIRMED LOW bug | Widgets/ExpiringBatchesWidget.php:36 | limit(10) baked into table query; same BackordersWidget:35 ReorderSuggestionsWidget:36
- [R2:#25] CONFIRMED LOW perf | Services/InventoryStatsAggregator.php:106-122 | Cache::remember with no stampede protection; short TTL, cheap reports
- [AUD:B1] ADOPTED MEDIUM perf | Resources/InventoryLocationResource/Schemas/InventoryLocationInfolist.php:83-93 | infolist 3 queries/view confirmed; CycleCountAction location revalidation OK :87-89
- [AUD:B2] ADOPTED PASS info | (package-wide) | G1 navigation PASS: getNavigationGroup on all resources, no static group
- [AUD:B3] ADOPTED MEDIUM perf | Resources/InventoryLevelResource.php:78-86 | DUP R2:#23; G2 uncached COUNT badges stand
- [AUD:B4] FALSE n/a perf | Resources/InventoryAllocationResource.php:42 | G3 contradicted package-wide: every resource uses with() (Movement:44 Level:45 Batch:48 Serial:49) plus widgets
- [AUD:B5] ADOPTED MEDIUM perf | Actions/ShipStockAction.php:40 | G4: 6 unpaginated location plucks across Ship/Adjust/CycleCount/Receive/Transfer stock actions
- [AUD:B6] ADOPTED LOW/MEDIUM perf | (global note) | G5 domain leakage cites no filament-inventory instance; global note only
- [AUD:B7] ADOPTED MEDIUM perf | Resources/InventoryLocationResource/Schemas/InventoryLocationInfolist.php:85-93 | DUP AUD:B1; G6 3 queries per view, use withCount/withSum


# Appendix — Cross-cutting verdicts (span multiple packages)

- [R1:#27] CONFIRMED medium bug | all four packages:tests/ | "Zero tests present" no tests/ dirs or *Test.php in any of the four packages
- [AUD:G1] ADOPTED none info | BaseCatalogResource.php:20 | PASS: config-based nav group/sort everywhere; recurs in all 3 filament audit files
- [AUD:G2] ADOPTED medium performance | ProductResource.php:46 | 3 uncached badge counts in products; no badges in seating/persons
- [AUD:G3] ADOPTED medium performance | CategoriesTable.php:41 | parent.name w/o with(); persons title.* cols likewise; seating clean
- [AUD:G4] ADOPTED medium performance | NamesRelationManager.php:56 | pluck+preload, relationship+preload whole-table loads; overlaps R1:#26
- [AUD:G5] ADOPTED none info | n/a | PASS for these pkgs: named instances only in vouchers/cashier-chip
- [AUD:G6] ADOPTED none info | n/a | PASS for these pkgs: named instances only in cashier-chip/inventory

