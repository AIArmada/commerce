# AIArmada Addressing — Bidirectional Reference Knowledge Lifecycle

**Developer handoff / technical proposal / initial implementation**  
**Prepared:** 15 October 2026  
**Repository:** https://github.com/AiArmada/commerce  
**Implementation reference:** `f071686821409b60b51f3b2646b1f7776b19e18b` (the `main` commit inspected immediately before this handoff)  
**Status:** REVIEWABLE IMPLEMENTATION FOUNDATION. Not a fully tested, production-ready merge. Read **Outstanding work** before deployment.

## 1. Why this project exists

`aiarmada/addressing` ships geographical reference information—countries, states, cities, areas, aliases, hierarchy, postcodes, and relationships—to consuming Laravel applications. **Initially, the package is the best available source of truth.** Over time, each production application encounters reality. Operators fix inaccurate coordinates, add previously missing localities, correct postcodes, add aliases, retire nonexistent areas, change relationships, or discover new geographical information from integrations.

The problem is not merely detecting package-seeder changes. The desired information flow is primarily **application → upstream package**, followed by **package → every consuming application** once a contribution has been validated and merged. Real-world local discoveries must improve the shared canonical dataset, while other applications must receive those improvements without losing their own independently discovered corrections.

### Non-negotiable outcomes

1. Identify **created, modified, and deleted** canonical reference records in an application, including edits made long after initial seeding.
2. Distinguish application edits from incidental database writes, reseeding, bulk imports, and package upgrades.
3. Preserve deleted-record evidence when the original record no longer exists.
4. Provide an administrator-friendly **Filament** review, comparison, approval, and ZIP export experience in production. No requirement to SSH into production to extract changes.
5. Submit validated, **portable** changes back into the package's source files, not production SQL dumps or app-specific database UUIDs.
6. Maintain human/source validation; application experience is an evidence-backed *candidate* for global truth, not necessarily authoritative for the whole world.
7. Publish an updated canonical reference dataset together with the package release and deliver it back to all applications.
8. Apply safe upstream changes automatically **only if local values have not diverged**; preserve independent local changes and flag conflicts.
9. Track package dataset revision, per-record baseline lineage, review decisions, and export history. **Exported is not the same as accepted upstream.**
10. Keep personal `addresses`, `addressables`, ownership information, snapshots, and customer address data out of canonical geography contribution exports.

## 2. Findings from the repository audit

The existing migration tables have `created_at` and `updated_at`, but timestamps alone do not achieve this aim:

- Deleting a row physically removes the timestamps.
- Update timestamps cannot reconstruct old values or explain *who changed what*.
- `SeedCountryGeographiesAction` deactivates provider areas before importing; subsequent reactivation may change timestamps without a meaningful geographical change.
- Some country-reference and area metadata synchronization deletes/recreates links, so IDs and timestamps can change even if the logical data is identical.
- Existing application corrections can be overwritten by reseeding, unless the seeding path becomes conflict-aware.
- An application upgrade through Composer does not automatically synchronize its database rows.

In the six commits inspected after the previous audit (`2809f4d` → `f071686`), Addressing gained manual area alias create/edit/delete actions, postal code CRUD and manual coverage management, richer imports/exports, improved Filament area administration, and new tests. These features are useful foundations but **do not implement complete upstream contribution tracking**.

Existing `address:export-gap-aliases` is a useful special-case precedent: it promotes matched resolution-gap aliases into provider-ready suggestions, but not arbitrary CRUD changes.

The newest Filament exporter remains a current-state exporter; it is not a differential export, and the area exporter is not a full graph export.

## 3. The agreed architecture

```text
           PACKAGE REPOSITORY / CANONICAL SOURCE
       provider PHP + source CSV/JSON + CI validation
                           |
                build immutable release ZIP
                           |
                 Composer package release
                           |
            +--------------+---------------+
            |                              |
       Application A                  Application B
     BASE / LOCAL / REMOTE          BASE / LOCAL / REMOTE
            |                              |
      Live corrections                Live corrections
            |                              |
   Filament change review           Filament change review
            |                              |
      Approved export                   Approved export
            +--------------+---------------+
                           |
                    Maintainer intake
             validate / compare / resolve
                           |
                 Provider-source PR
                           |
                     merge + release
                           |
              applications re-synchronize
```

### Three-way comparison

**BASE** = the canonical version each record last received or confirmed.  
**LOCAL** = the consuming application's current DB representation.  
**REMOTE** = the newest canonical reference bundle delivered by the installed package.

Each record has a stable *natural key* and a normalized payload checksum; DB UUIDs, `created_at`, `updated_at`, generated slugs, and seed `synced_at` are intentionally not treated as geographical facts.

| Base | Local | Remote | Proposed outcome |
|---|---|---|---|
| Absent | New X | Absent | Upstream **CREATE** candidate |
| X | Changed Y | X | Upstream **UPDATE** candidate |
| X | Absent | X | Upstream **DELETE** candidate (tombstone from baseline) |
| X | X | Changed Y | Incoming safe **UPDATE** candidate |
| Absent | Absent | New X | Incoming safe **CREATE** candidate |
| X | Changed Y | Same Y | Upstream correction already adopted: **RECONCILED** |
| X | Changed Y | Different Z | **CONFLICT**: never overwrite automatically |
| X | X | Absent | Incoming **RETIRE** candidate; manual dependency review |
| X | Absent | Absent | Removal already reconciled |

Important: a rename of a codeless state or a city (currently keyed by name) can look like a delete plus a create. Agents should design explicit identity migration/alias policies before allowing such renames as automatic writes.

### Content versus events

This implementation captures **net changes compared to a stored baseline**, rather than every intermediate operation. If an administrator adds and then deletes a temporary record between comparisons, the final dataset has not changed and no contribution is emitted. This is appropriate for upstream canonical data *diffs*. If a complete forensic history or regulatory audit is required, add an append-only event journal/CDC or database triggers separately.

## 4. Data boundaries and portable identity

The provided prototype captures these **ten** reference entity types:

- `countries`: `iso2`
- `states`: country ISO + stable subdivision code, falling back to a name-based key
- `cities`: country ISO + subdivision identity + city name
- `areas`: `country_code + source + source_id`
- `area_names`: owning area identity + alias source + alias kind + alias text
- `area_roles`: owning area identity + role source + role
- `area_relationships`: parent and child area identities + source + relationship/hierarchy types
- `area_state_links`: area identity + state identity + hierarchy type
- `postal_codes`: country ISO + code
- `area_postal_codes`: postcode identity + area identity + source/source_id + relationship type

**Not yet covered:** `country_currency_links`, `country_timezone_links`, source-controlled validation/format profile changes, or any external location data not persisted in the above tables. These should be evaluated before declaring full data coverage. `addresses`, `addressables`, `address_snapshots`, `address_area_assignments`, and owner-scoped resolution history are **deliberately excluded** from global reference exports; selected resolution-gap aliases can still use the existing special-case exporter.

Because the source package may use UUIDs differently in each application, the exported identities refer to sources and natural geographical keys. The maintainer must translate them into appropriate provider/CSV references. Do not apply raw app UUIDs upstream.

## 5. What is included in this ZIP

All paths below are relative to the **monorepo root**, so agents can copy the `packages/` and `tests/` directories into a feature branch. Existing files are supplied as **complete proposed replacements** against the inspected revision—not an automatic modification of the remote repository.

### Core `packages/addressing`

| File | Purpose |
|---|---|
| `database/migrations/2026_10_15_000001_create_reference_sync_tables.php` | Durable baseline/local/remote snapshots, review decisions, settings, and export-batch history |
| `src/ReferenceSync/Canonical.php` | Deterministic JSON serialization and hashing |
| `src/ReferenceSync/ReferenceTypes.php` | Bounded, portable projection of reference tables and natural keys |
| `src/ReferenceSync/SnapshotRepository.php` | Capture current app data, import remote snapshot, initialize pristine baseline, advance individual accepted baselines |
| `src/ReferenceSync/DiffEngine.php` | Calculate contribution/delivery queues with conflict classification and approval invalidation |
| `src/ReferenceSync/ReferenceReview.php` | Review/approve/reject changes |
| `src/ReferenceSync/ReferenceArchive.php` | Canonical release and approved-contribution ZIP format; SHA-256 integrity checks |
| `src/ReferenceSync/ReferenceWriter.php` | Conservative, locked apply of approved safe incoming changes; intentionally defers unsafe graph/retirement operations |
| `src/Jobs/ReferenceSyncJob.php` | Queued capture, release import, baseline bootstrap, and safe apply; serialized using a shared cache lock |
| `src/Commands/BuildReferenceDatasetCommand.php` | CI/operator command to build canonical package archive from a clean seeded database |
| `src/Commands/InspectReferenceContributionCommand.php` | Maintainer-facing integrity validation and JSON review-report generation for untrusted contribution ZIPs; never mutates sources automatically |
| `src/AddressingServiceProvider.php` | Registers new services and maintainer commands |
| `composer.json` | Declares PHP `ext-zip` dependency |

### Filament `packages/filament-addressing`

| File | Purpose |
|---|---|
| `src/Pages/ReferenceSyncPage.php` | Secured Filament page with review queues, two directions, export, release loading, and queued apply |
| `resources/views/pages/reference-sync.blade.php` | Filament admin presentation with before/local/remote diff and review controls |
| `src/FilamentAddressingPlugin.php` | Conditionally registers the new page |
| `src/FilamentAddressingServiceProvider.php` | Registers the package's Blade views |
| `config/filament-addressing.php` | Feature is **disabled by default** (`features.reference_sync=false`) |

### Tests

`tests/src/Addressing/Actions/ReferenceSyncFeatureTest.php` contains initial Pest tests for timestamp immunity, additions/deletions, upstream acceptance, approval fingerprint invalidation, and failed baseline bootstrap. The repository's existing `tests/Pest.php` binds `src/Addressing/Actions` to the slim addressing database test case.

## 6. Filament production workflow

Create a dedicated **Geography Contributions & Updates** page rather than changing the meaning of existing CSV exporters.

The page supports:

- **Contributions (App → Package)** and **Updates (Package → App)** queues.
- Filter by pending, approved, rejected, or all.
- Per-record `BASE`, `LOCAL`, `REMOTE` details and detected operation.
- Explicit approve/reject actions; a changed fingerprint resets earlier approvals.
- Export only approved, non-conflicting contribution records.
- Load the release archive **from the installed package**, never from arbitrary browser-uploaded content.
- Compare live references to stored baselines and rebuild both queues.
- Initialize a baseline only when the current DB exactly equals the trusted bundled canonical snapshot.
- Apply up to 100 approved *safe* incoming changes in a queued operation, with last-moment row-content verification.
- Recent contribution export batch history.

Production scale requires asynchronous operation: collection, diff and update jobs are queued (`ReferenceSyncJob`) rather than being executed inside the Filament HTTP request. The queue worker and distributed cache lock must be configured correctly. Jobs report status in `addressing_reference_settings`.

### Required authorization

The feature defaults to disabled. An application explicitly enables:

```php
// config/filament-addressing.php
'features' => [
    // ... existing options
    'reference_sync' => true,
],
```

The page additionally checks the ability `addressing.manage-reference-sync` against the authenticated user. Hosts must grant it **only to trusted geography administrators**; for example using an application Policy/Gate or a permission package. An operator's access to other Filament resources must NOT automatically grant this power.

### Required infrastructure

- PHP **8.5+** as required by the inspected repository; **ext-zip** installed.
- Laravel migrations run, database backed up, appropriate SQL capacity for large snapshot tables.
- Queue worker serving `default` (or configured `filament-addressing.reference_sync.queue`).
- Shared cache-lock backend (Redis/database; avoid non-shared array locks in multi-worker production).
- Writable private storage for exports; do not serve export archives from a public web directory.

## 7. Exported contribution bundle format

The ZIP contains exactly:

```text
manifest.json
records.ndjson
```

The manifest specifies `schema=1`, `kind=contributions`, `revision` (baseline release), `batch_id`, `count`, SHA-256 of the exact NDJSON bytes, and creation time. Each line contains:

```json
{
  "entity_type": "postal_codes",
  "natural_key": ["MY", "47500"],
  "operation": "update",
  "before_revision": "package-v1",
  "before": {"country_code":"MY","code":"47500","is_active":true,"metadata":null},
  "after": {"country_code":"MY","code":"47500","is_active":true,"metadata":{"verified":true}},
  "base_checksum": "<64-char SHA-256>",
  "fingerprint": "<64-char change SHA-256>",
  "notes": null
}
```

This is an **illustration**, not factual proof about postcode 47500. `natural_key` is the stable portable identity; no app row UUID. The data may contain fields beyond those shown (e.g., provider-specific metadata). A deletion sets `after` to `null` and preserves `before`, making physical deletion detectable.

**Do not accept uploads merely because the SHA-256 matches.** The checksum detects accidental corruption, not malicious forgery. Application identities, submitter permission, source credibility, and geographic validity require separate authentication, authorization, and maintainer review.

## 8. What happens after Filament export? (Upstream)

1. **Production administrator** reviews candidate changes and downloads the approved ZIP in Filament. The export is logged as `exported`, not `merged`.
2. **Maintainer/agent** places the ZIP in a trusted work area. Do not execute or import it into production SQL.
3. Validate structure and checksums using:

   ```bash
   php artisan address:reference-inspect /path/to/contributions.zip \
     --report=/tmp/addressing-contribution-review.json
   ```

   The report contains structured before/after proposals, key identities, counts, baseline versions and notes. The command is **validation + report only**.
4. Compare each exported `before` payload and `before_revision` against the **actual current canonical provider/CSV source**. If it already matches the proposed `after`, mark reconciled. If the current package differs independently, classify a conflict. Never blindly apply an old ZIP.
5. Validate geographical evidence, ISO subdivision rules, uniqueness, parent/child links, duplicate areas, multi-locality postcodes, forward and reverse references, and locale alias collisions. Disagreement between applications is a review signal, not a winner-takes-all timestamp race.
6. Transform approved candidate changes into a Git feature branch modifying the authoritative `packages/addressing` geography providers and source CSV/JSON. Add regression tests. **A direct provider-source patch compiler is not included in this prototype** because countries use heterogeneous provider structures and indiscriminate conversion is unsafe.
7. Submit a PR to `AIArmada/commerce`, run provider consistency tests and new update tests, review, and merge.
8. Build a **new immutable canonical reference release** on an isolated database that is freshly seeded from the merged source files. Do not build from a production app DB.
9. Publish that bundled reference archive with a new package version. Record source commit, dataset revision, checksums, and reproducible build metadata.
10. Optionally send a **signed incorporation receipt** listing accepted per-record fingerprints and their released revision back to the submitting application. This needs a follow-up implementation (see §12).

### Build a release archive

From a CLEAN isolated package test application with all canonical sources seeded, run:

```bash
php artisan address:reference-build package-v2 \
  --output=packages/addressing/resources/datasets/current.zip
```

The release archive embeds normalized records and version `package-v2`. **This command cannot certify that the DB was freshly seeded**; the CI/release workflow must enforce that by using a newly provisioned database and known source commit. Build artifact generation and committing it into the release are separate CI/repository actions that agents must wire into the existing monorepo split/tag workflow.

`resources/datasets/current.zip` is **not included in this handoff** because the package's complete seeded dataset was not available in the execution environment. No fabricated canonical snapshot is supplied.

## 9. How new data returns to applications (Downstream)

Initially use **Composer release delivery** rather than a new centralized online reference-data service.

1. Application deploy upgrades `aiarmada/addressing` and `aiarmada/filament-addressing` and runs migrations as usual.
2. Filament → Geography Contributions & Updates → **Load packaged release** queues a job that verifies `resources/datasets/current.zip`, checks digest and record count, and imports the package's canonical remote snapshot. No application geography is mutated in this step.
3. **Queue comparison** captures `LOCAL` and generates a three-way diff against saved `BASE` and new `REMOTE`.
4. Operators inspect all differences. Acceptable new/updated scalar country/state/city/area/postcode records may be approved for safe apply; competing local corrections become conflicts and cannot be approved as normal updates.
5. **Apply approved safe updates** runs in the queue, verifies no intervening local write, changes model rows through Eloquent, and advances **only that record's** baseline after successful commit.
6. Incoming retirement, relationship graph mutations, and identity changes are intentionally **deferred** until dependency-aware application logic exists. Existing customer addresses must never be silently deleted.
7. Repeat review until remaining records are reconciled, excluded by policy, or pending manual conflict resolution. Do not advance a global baseline indiscriminately while there are outstanding conflicts.
8. Recheck contributions: local values already accepted upstream should vanish from the pending contribution list because `LOCAL == REMOTE`.

**Do not rerun ordinary legacy seeders against live modified data as a substitute for this process.** Their existing mutation behavior can still overwrite local corrections. Until the standard seeders are integrated with the same conflict-awareness, control their use in production through operational policy. The provided implementation **does not change the legacy seed actions**.

Longer term, canonical dataset updates could be signed, separately versioned packages/feeds, independent of Composer code releases; the same three-way engine can be reused.

## 10. Baseline initialization and existing production installations

This is the most important migration/rollout constraint.

A new pristine application can seed its reference data, load the **matching** canonical release, and initialize the baseline. Initialization checks **both directions** (no missing, added or differing rows) before persisting it.

An existing application whose reference data was already modified **must not** initialize its baseline from its present database: that would destroy the distinction between original canonical truth and discovered production improvements. Obtain the **exact historical canonical release** (package commit/version originally seeded) and construct its baseline separately. The current bootstrap method intentionally rejects a mismatching DB. An historical-baseline import command remains required for a truly general migration path; the new code does not invent the missing history.

If earlier hard deletions occurred without a surviving original dataset, they cannot always be reconstructed: data recovery, archive reconstruction, or operator attestation may be necessary.

## 11. Review and rollout plan for development agents

### Phase 1: Correctness and integration

- Check every full replacement file against the actual inspected Git commit; resolve upstream drift.
- Run `composer validate`, syntax/lint (PHP 8.5), Pint/PHPStan, Pest addressing tests, and Filament Livewire component tests.
- Verify primary-key handling and source keys against real migrations (especially codeless states, alias names, area state links, and graph edges).
- Create clean release snapshots on seeded test DBs and run a full round-trip on a sample MY dataset.
- Validate relational consistency when a country/state/area is updated, and check lat/lon JSON/float canonicalization across MySQL, PostgreSQL, and SQLite.
- Exercise ZIP validator with corrupted archive, bad checksum, incorrect kind, very large rows, duplicate keys and malicious metadata.

### Phase 2: Production safety

- Integrate the established reference-sync lock or equivalent write barrier with **all legacy seeding and bulk-import paths**. Snapshot comparisons require a logically stable reference dataset.
- Add stronger transactional/concurrency regression tests and a uniqueness check for natural keys across each source.
- Make upstream build fully reproducible from source data on an isolated DB with deterministic dataset revision and signed artifact provenance.
- Implement a **dedicated, paginated Filament diff comparison** with field-level additions/removals and source evidence, instead of the current raw JSON detail.
- Implement conflict-resolution choices: keep local, adopt canonical, propose corrected upstream version, or mark intentional local-only override. Track reviewer rationale.
- Implement targeted tests for the queued job, lock, permissions, background progress, export history and binary download response.
- Benchmark memory, DB indexes, snapshot sizes and job time for >800,000 postal codes and millions of coverage edges. A full snapshot on every review is a prototype choice and may need incremental or checksum-join optimizations.

### Phase 3: Complete round-trip

- Implement full relationship-graph create/update/retire mapping and dependent-key migration; never create or orphan dangling graph references.
- Add dataset-scope country currency/timezone reference links if in scope.
- Add package release workflow / CI generation of `current.zip`, checksum, immutable revision and automated test vectors.
- Build a **canonical source patch compiler** for supported provider formats, or explicit maintainer review adapters for non-uniform country providers.
- Add per-contribution incorporation receipts with Git commit/release identity, accepted/rejected fingerprints, application-side ack, export history updates, and optional PR automation.
- Add safe historical-baseline loading for applications already in production, with audit/report-only mode before first write.
- Include opt-in automation for daily change scans and release detection; Filament remains the review/approval experience.

## 12. Known incomplete or intentionally restricted implementation

**Do not represent this ZIP as a complete production-ready feature.** It is working-shaped PHP source and a reviewable vertical slice, with deliberately conservative boundaries:

1. **No direct source-patch compiler or automatic GitHub PR.** The maintainer intake command validates and reports; agents still need to map changes to heterogeneous provider source files and open PRs.
2. **No remote acceptance receipts.** Exports receive batch IDs and local history, but incorporation/rejection from Git commits isn't yet automatically acknowledged back to applications.
3. **No complete canonical release artifact or CI generation workflow.** A clean seeded environment and repository-specific release step are prerequisites.
4. **No production upgrade of historic local baselines.** Fresh bootstrap insists on pristine data; existing deployments need original historical package snapshots.
5. **No automatic delete/retirement or relationship/pivot application.** These changes are detected and reviewed, but applying them without resolving dependencies could corrupt existing addresses.
6. **No suppression of legacy seeders.** Operators must not run them casually on locally modified production reference data until integrated.
7. **No immutable, global event journal.** This is net-state diff, not every intermediate CRUD event or a legal-grade audit log.
8. **No isolated global snapshot transaction for the entire scan.** A shared job lock serializes sync jobs but does not stop unrelated writes; ingestion and scan concurrency should be hardened before launch.
9. **No guaranteed deterministic capture for every database dialect.** Review `JSON`, floats, dynamic table prefixes and hierarchy provenance using real fixtures.
10. **No full integration test was executed during handoff creation.** PHP syntax was checked using the available PHP 8.4 runtime, but the repository requires PHP ^8.5, Composer dependencies were unavailable, and external GitHub checkout/network access was unavailable. Pest/Laravel/Filament runtime tests must be executed by development agents in the actual repository.

These limitations are **not optional footnotes**. They determine whether the feature is safe to activate in production. `features.reference_sync` deliberately defaults to `false`.

## 13. Exact deployment guidance for agents

1. Create a new feature branch from a known compatible repo commit (prefer the inspected `f071686` if it is still available); copy the file tree from this ZIP into monorepo root.
2. Check Git diffs of all **full replacement files** carefully against HEAD; do not overwrite newer unrelated changes.
3. Install PHP 8.5 and zip extension. Validate Composer dependency constraints.
4. Run migrations in disposable test environment and execute the included Pest suite and existing Addressing/Filament Addressing tests.
5. Build a clean canonical release archive from a newly seeded test DB, inspect natural keys and confirm record counts across all 10 entity kinds.
6. Test package→application and application→package separately, with real conflicts and hard deletions.
7. Wire release builds and work through remaining deployment blockers in §11/§12.
8. Only after operational sign-off: grant the gate, configure queue worker/cache lock, enable Filament feature flag for a trusted admin panel, and roll out **read-only review/export first**. Enable downstream apply in stages after verification, backup and rollback drills.

## 14. Key architecture decisions to preserve

- **Package initially owns truth; experience enriches it.** The application's local truth must remain protected until reviewed and accepted, not blindly overwritten.
- **Review in Filament** for production; CLI for build automation and maintainer intake only.
- **Canonical reference entities only.** User addresses are not global geographical facts and must not leak upstream.
- **Stable natural identities and per-record baseline lineage**, never ephemeral row IDs or timestamps alone.
- **Contributions are proposals.** Export ≠ merge; merge ≠ release; release ≠ application DB update.
- **Three-way merge for every downstream synchronization.** Match the current local record to the actual recorded baseline and reject unreviewed divergence.
- **Immutable provider/CSV source data plus a reproducible package release** as the authoritative delivery mechanism.
- **Explicit approvals, conflicts, tombstones, rollback planning, and secure distribution** are requirements, not optional interface polish.

---

**Short developer brief:** Implement a versioned, baseline-aware, Filament-operated, bidirectional *knowledge contribution cycle* for AIArmada's canonical address/geography reference data. Build on the prototype in this ZIP, preserve all existing Addressing contracts and data, and finish the hardening and release integration items before enabling writes in production.
