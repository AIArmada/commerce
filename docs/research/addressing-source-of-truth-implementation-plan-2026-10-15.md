---
title: Addressing Source of Truth — Implementation Plan and Agent Handoff
status: implementation-plan
prepared: 2026-10-15
repository: .
reviewed_head: 32e422e77c45f2fb8cf71ee0ffb8854bf95d3153
---

# Addressing source of truth: implementation plan

> **Scope update — authoritative implementation direction**
>
> A later user clarification supersedes implementation of the 12-slice runtime synchronization system described below. Implement only these three focused deliverables: (1) make the existing reseeding pipeline safe, repeatable, and performant; (2) add a reference-only export and offline comparison workflow for occasional AI-assisted source contributions; and (3) add regression and performance verification for those behaviors. Application data is occasional evidence for proposed package-source changes; it is not continuously synchronized back to the package. Keep reseeding deterministic and responsible for preserving local data and reporting conflicts. No backward compatibility or legacy paths, incremental migrations, or data backfills. Edit owning migrations in place when a schema change is necessary. The remaining architecture and 12-slice detail below is historical context, not a requirement to build the runtime correction, approval, release-installation, or downstream synchronization system. Where it conflicts with this scope note, this scope note governs.

## 1. Assignment and outcome

Implement a reliable reference-data lifecycle for `aiarmada/addressing` and its Filament adapter. The approved direction is:

> Reviewed package sources in Git define shared canonical geography. Published datasets are reproducible derivatives. Applications retain explicit local corrections. Corrections become shared canonical facts through evidence, a reviewed source change, and a new package release.

Deliver the work in the ordered slices below. The first usable release provides clean canonical installation, reliable correction capture, review, and export. The complete implementation adds safe package updates through the same mutation boundary. Keep downstream application disabled until its acceptance tests pass. Do not stop after cosmetic fixes to the current prototype.

The user requested a plan for another implementation agent. No production code was changed while preparing this document. This document authorizes no production deployment, data rewrite, package publication, dependency change, or external communication. Implement and verify locally; follow the user's subsequent implementation instructions for Git/PR actions.

### Explicit user constraints added during planning

- No backward compatibility or legacy behavior.
- Edit existing owning migrations for schema changes.
- Remove superseded prototype code and protocol rather than maintaining both.
- No historical application-data adoption or conversion. Preserve only revision history produced by the new system itself.

### Success criteria

1. An application can identify which canonical dataset and scope each managed record came from.
2. A local correction records original canonical values, changed fields, actor, time, reason, and evidence separately from canonical values.
3. Existing model queries continue to return effective application values without requiring consumers to join override tables.
4. Reseeding or installing a package update cannot silently erase local corrections.
5. Review approval applies to precisely the content shown to the reviewer.
6. Applying a release preserves local database IDs and keeps geography relationships consistent.
7. Contributions never contain instance addresses, customer data, owner tuples, authentication material, or arbitrary source payloads.
8. A release can be rebuilt from authoritative sources with the same logical dataset digest.
9. Initialization supports only the new canonical schema and protocol. No prototype-data conversion, historical-baseline adoption, compatibility adapters, or fallback write paths exist.
10. Scoped tests and a real release/contribution round trip prove these properties.

## 2. Required reads and repository constraints

Read the current files, not only this handoff:

- `AGENTS.md`, `CONTEXT-MAP.md`, `.ai/rules/index.md`.
- Matching area rules and keyword search across `.ai/rules`.
- `packages/addressing/CONTEXT.md` and all five canonical docs.
- `packages/filament-addressing/CONTEXT.md` and all five canonical docs.
- `packages/commerce-support/CONTEXT.md` before introducing shared infrastructure.
- `docs/agents/domain.md`; relevant existing ADRs if present.
- `.ai/rules/addressing-seeding.md`, `.ai/rules/country-data-expansion.md`; Malaysia rules only when touching Malaysia sources/tests.
- `packages/addressing/docs/05-country-data.md`, `19-reference-pipelines.md`, and relevant provider docs.

Use codebase-memory-mcp for code discovery before text search. The graph was indexed during planning but may be stale; validate exact source before edits. Confirm installed versions before using framework APIs. At review time: PHP 8.5.10, Laravel 13.34.0, Filament target v5. Use Context7 when looking up framework/library APIs.

Important constraints:

- Core Addressing owns data and mutation rules; Filament only adapts them.
- Keep `SeedAddressingAction` as the composition root and the bundled `AddressingSeeder` as the public seeding entry point.
- Preserve provider/source ownership semantics and source-owned versus manual links.
- Reference geography is global. Instance addresses, snapshots, addressables, assignments, and gaps retain their existing owner contracts.
- Global correction powers belong only to trusted geography administrators; tenant admin membership alone grants nothing.
- UUID primary keys; no database foreign-key constraints/cascades; no soft deletes.
- Use `AddressingTableResolver` for every new table. Honor configured model classes and table remapping.
- Honor `addressing.database.json_column_type`; use immutable lifecycle timestamps.
- Edit the owning migrations in place, including `packages/addressing/database/migrations/2026_10_15_000001_create_reference_sync_tables.php` for reference-management tables. Do not add incremental schema-change migrations, data migrations, backfills, upgrade scripts, or old-column detection. New tables/columns start with clean semantics. Do not execute a destructive database rebuild as part of implementation without separate user authorization.
- Do not add dependencies. Existing `ext-zip` stays required for this implementation; optional packaging/dependency changes are a separate decision.
- Keep domain infrastructure in Addressing. No generic synchronization framework in commerce-support without a demonstrated second consumer.
- No request-leaking mutable static state or process-global origin flags.
- Every Pest invocation includes `--parallel`, with output captured through `2>&1 | tee ...`. Scope tools to modified packages/files.

## 3. Starting state and verified defects

The reviewed commits are:

- `a274a2326cb28a853ce422b356c471a1597fb4d1`: introduces the prototype and a handoff document.
- `32e422e77c45f2fb8cf71ee0ffb8854bf95d3153`: strengthens some checks, adds docs, removes the initial handoff.

Read both diffs. The deleted original handoff is available through Git history; do not restore it as an implementation specification because this plan supersedes its architecture.

At the reviewed HEAD, seven existing `ReferenceSyncFeatureTest` tests passed and scoped PHPStan passed. Five additional regression tests failed:

| Failure | Reproduction | Required result |
| --- | --- | --- |
| Missing state | Incoming release contains a new state and city; approve only the city | Defer city, preserve its intended state reference, do not advance its baseline |
| Self-parent | Incoming area update sets its parent to itself | Reject before mutation |
| Broken containment | Move an area to an existing valid parent, with the matching incoming edge change | Parent pointer and source-owned containment link change together, or the entire unit is deferred |
| Approval race | Render change A, rebuild same ID with payload B, then approve using old screen | Reject stale approval; B remains unapproved |
| Starvation | First approved item is unsupported/deferred; a later scalar item is applicable | Eligible later item can progress; repeated runs do not select only the blocker |

Temporary reproduction sources and audit logs were used during review but are not part of this repository. Do not depend on those local artifacts surviving; reproduce any needed behavioral cases from the descriptions below.

Other confirmed defects/gaps:

- `reference-sync.blade.php` passes UUIDs unquoted to review buttons.
- `ReferenceSyncJob` allows 7,200 seconds while its lock expires after 3,600.
- Lock contention throws before job-state reporting and exhausts the only attempt.
- Queue visibility/retry timing is not defined for these long operations.
- No packaged `resources/datasets/current.zip` or build/distribution integration exists.
- Pristine-only baseline initialization cannot onboard already-edited applications.
- Existing seed/import paths can overwrite reference values independently of sync; replace these paths rather than preserving a legacy mode.
- Applying 100 records rebuilds twice; each rebuild scans reference data twice. The prototype therefore performs four full scans per apply batch.

## 4. Architecture decisions to implement

### 4.1 One authority, explicit provenance

| Concept | Meaning |
| --- | --- |
| Canonical source | Reviewed provider PHP and source CSV/JSON plus a committed identity catalog |
| Release | Immutable dataset manifest and scoped record artifacts derived from canonical source |
| Installed canonical record | Last successfully installed canonical content for an identity |
| Local correction | Explicit field changes, local create, or retirement proposal with provenance |
| Effective projection | Existing geography tables used by current readers |
| Contribution | Frozen reviewed proposal exported for maintainer assessment |
| Incoming release | Verified candidate data; loading it never changes effective geography |
| Unmanaged drift | Live data changed outside the controlled mutation boundary |

The existing geography tables remain the effective projection. Canonical payloads and correction payloads are stored separately in reference-management tables. This avoids retrofitting every `where`, relationship, aggregate, and selector with overlay joins.

For a non-conflicting record, effective values equal installed canonical values with active explicit correction fields applied. Unmodified fields inherit canonical values. An explicit null differs from an omitted field. Arrays are replaced as complete field values; do not introduce arbitrary nested JSON merge semantics in v1.

All supported writes update correction/canonical state and the effective projection transactionally through the shared mutation boundary. Do not add an after-the-fact observer as the primary integrity mechanism: existing bulk query-builder paths bypass model events.

Unmanaged SQL or custom model writes cannot be made impossible by this package. Detect them as drift, show them for review, and block affected canonical writes until explicitly resolved. Never silently convert them into canonical facts or authenticated corrections.

### 4.2 Scope is part of identity and deletion semantics

Use explicit dataset scope keys:

- `{ISO2}:core`: country, states, country currency/timezone links.
- `{ISO2}:cities`: city tier.
- `{ISO2}:areas:{providerKey}:{variant}`: areas, aliases, roles, containment/other relationships, state links.
- `{ISO2}:postcodes:{datasetKey}`: postcodes and coverage links.

The manifest declares scope dependencies and completeness. The default variant is explicit; optional Indonesia villages belong to a distinct declared variant/scope selection. Do not infer scope from runtime environment or scan results.

Applications record installed scopes. Non-production city filtering, disabled postcodes, omitted countries, custom providers, runtime OneMap records, and uninstalled variants are not deletions. A missing identity becomes a retirement candidate only when comparing two proven complete versions of the same installed scope. Preserve runtime/provider records outside package-managed scopes.

Each portable record has one declared owning scope. Duplicate identities across artifacts are a build error. Cross-scope relationships reference durable IDs and declare required scopes.

### 4.3 Durable identity

Local database UUIDs remain application-specific and never serve as shared identity.

Introduce a committed identity catalog for canonical nodes. Assign portable UUIDs once and retain them when names, codes, source locators, or parents change. Current natural keys are source locators used for matching/diagnostics, not perpetual identity.

Suggested location: `packages/addressing/resources/data/reference-identities.ndjson`, sorted by entity type and portable ID. Each entry includes `reference_id`, `entity_type`, `scope_key`, current source locator, retired marker when relevant, and explicit replacement metadata when a future canonical identity is intentionally retired or replaced. Partition the catalog by scope if its measured size makes one file impractical; the format and semantics remain the same.

Rules:

- Build verification fails on a source row with no identity, duplicate identity, ambiguous locator, unexplained disappearance, or identity reuse.
- A dedicated explicit allocation operation may propose catalog entries for new source records. Ordinary builds never mint IDs automatically.
- A rename updates its locator under the same identity. Splits/merges require explicit replacement mappings; no fuzzy automatic reassignment.
- Relationship identities may be deterministically derived from durable endpoint IDs, owning source, relationship kind/hierarchy, and a documented immutable discriminator. Changing that tuple is an explicit remove/add in one graph change set.
- Alias text can be modeled as remove/add under the owning area. A name correction must not rename the area identity.
- Local additions receive portable proposal UUIDs separate from physical row IDs. A released acceptance mapping connects them to the eventual canonical identity; never guess a match by name.
- Preserve existing physical country/state/city/area/postcode IDs when changing canonical attributes. Preserve all instance-tier references.

Do not bulk rewrite runtime IDs or infer replacements from pre-existing null columns. New bindings are created by the new installer; there is no importer for prototype identities.

### 4.4 Scalar corrections and topology

Support scalar updates through explicit field allowlists. Identity, owning scope, source ownership, and relationship endpoints cannot be changed as arbitrary scalar fields.

Treat reparenting, coverage moves, primary-postcode changes, state links, identity replacements, and retirement as graph change sets. Each set has a dependency list and one atomic validation/application boundary. Do not apply its area pointer while deferring its relationship row.

Reuse `AddressAreaHierarchy`, `SaveAddressAreaAction`, `SavePostalCodeAction`, importer validation, and `GeographyReferenceCleanup`. Extract common validation/persistence operations where needed so UI and canonical updates invoke the same rules. Avoid a second copy of domain rules inside a generic writer.

The first usable milestone reports graph proposals but does not apply them. The complete milestone implements atomic graph units. Unsafe or unsupported units stay explicitly blocked with reasons.

### 4.5 Trusted global administration

Keep the explicit `addressing.manage-reference-sync` gate. Add a separate `addressing.apply-reference-release` ability for downstream writes. Core commands are trusted operator entry points; document that boundary and require explicit actor/provenance on application-facing correction calls.

All authorization happens again in server-side handlers. Reference corrections affect global geography; do not add misleading owner tuples to them. Instance-tier maintenance retains explicit owner-safe or documented cross-owner handling.

## 5. Portable records, manifests, and publication

Replace the prototype's permissive `SELECT r.*` minus a denylist with per-type portable schemas. Cover all twelve reference types currently enumerated in `ReferenceTypes::TYPES`.

### Canonical record envelope

```json
{
  "entity_type": "cities",
  "reference_id": "<portable UUID>",
  "scope_key": "MY:cities",
  "locator": {"country": "MY", "state_code": "10", "name": "Example city"},
  "attributes": {"name": "Example city", "latitude": null, "longitude": null},
  "references": {"country": "<portable country UUID>", "state": "<portable state UUID>"}
}
```

The example specifies shape, not real geographic data. Reference null is allowed only when the domain explicitly allows no relation; a missing required referenced record is an error.

Per-type rules:

- Countries: approved country descriptive fields/translations only.
- States/cities: descriptive fields and coordinates; country/state references use portable IDs.
- Areas: descriptive fields and approved geographic metadata; parent is a portable reference.
- Names/roles: owning area reference, source provenance, and the relevant descriptive/role attributes.
- Relationships/state links/coverage: portable endpoint references and typed link attributes.
- Postcodes: normalized country/code, active state, and approved public geographic metadata.
- Country currency/timezone links: country portable ID plus existing commerce-support currency code/timezone name. Addressing does not take ownership of currency/timezone source datasets.
- Exclude physical UUIDs, write/sync timestamps, generated/custom slugs, owner data, arbitrary `source_payload`, free-form metadata, actor identities, and customer data. Review notes/evidence need their own validated contribution fields.

Normalize every field explicitly: nulls, booleans, integers, strings, numeric precision, Unicode encoding, and set ordering. Keep list order only where semantically significant. Never hash database-driver formatting. Persisted coordinates must reserialize to the same normalized value. Test SQLite, MySQL, and PostgreSQL for the portable normalization surface.

### Release manifest

Schema v2 is a deliberate replacement; reject v1 archives with a clear rebuild instruction. Do not retain two parallel runtime protocols.

Use `resources/datasets/manifest.json` with scoped artifacts below `resources/datasets/`. Include:

- schema version, immutable dataset revision, authoritative source-tree digest, originating source commit;
- portable-schema/profile digest, identity-catalog digest;
- scopes, variants, dependencies, completeness, per-scope counts, record digests and artifact paths;
- explicit retirements and replacement mappings;
- accepted contribution fingerprints and proposal-to-canonical identity mappings when applicable.

The authoritative source-tree digest excludes generated datasets, generated build reports, and mutable timestamps. This avoids a self-referential artifact/commit hash. Define the input file list in code and test it.

Sort records by portable identity. Normalize archive member order, timestamps, and permissions. Pin the build environment. Two clean builds must produce the same logical digest; ZIP bytes must also match in the pinned environment. Wall-clock timestamps and random batch IDs cannot affect canonical release identity.

Build only in an isolated disposable database with no application configuration, owners, local corrections, or network-fed runtime data. Explicitly seed complete declared scopes. Reject an initialized managed application database as build input. Fail on dangling references or incomplete declared scopes.

Commit validated generated datasets before tagging. The existing monorepo splitter only transports the tagged tree, so generating an untracked archive during split is insufficient. Add a PR/build workflow that rebuilds and verifies datasets, then make the addressing split fail if the manifest/artifacts are missing or stale. Do not introduce another publishing service.

Retain new-protocol release artifacts through immutable Git tags/package versions for provenance and future reconciliation. This does not authorize support for an older protocol or an importer for pre-existing application data. No automatic download from arbitrary user-provided URLs.

## 6. Storage design

Implement the logical tables below using configured names. Reuse the existing reference migration and storage where appropriate instead of leaving the prototype tables and a duplicate new system active side by side.

| Table/key | Required content and invariants |
| --- | --- |
| `reference_releases` | UUID PK, unique revision, source/schema/catalog digests, verified manifest, status, verification/completion timestamps |
| `reference_scopes` | UUID PK, unique scope key, installed revision/completeness, generation/version counter, scope status and last completed run |
| `reference_bindings` | UUID PK, unique `(entity_type, reference_id)`, scope, origin `canonical/local/external`, physical row ID when materialized, expected projection checksum, current correction pointer, version counter |
| `reference_baselines` | Existing table becomes installed canonical payload/checksum/revision keyed by durable identity; include explicit canonical retirement state where relevant |
| `reference_remotes` | Verified candidate records partitioned by release/run and scope; separate generations until activation, never overwrite a still-visible candidate generation |
| `reference_overrides` | UUID PK, reference ID/type, monotonically increasing correction version, operation `update/create/retire`, explicit changed fields, original canonical values/checksum/revision, proposed values, reason/evidence, actor, status, lifecycle timestamps; content of a reviewed version is immutable |
| `reference_changes` | Derived review candidates with direction, change-set ID, exact fingerprint, review decision, reviewer/notes/timestamps and explicit block reason; not authoritative canonical storage |
| `reference_runs` | UUID PK, operation, actor, scope/candidate generation, status, cursor/progress, mutation-version token, bounded error and lifecycle timestamps |
| `reference_exports` | Existing table stores frozen approved export digest, revision/scope provenance, immutable file identity/path, counts, status and lifecycle timestamps |

`reference_settings` may hold small metadata only if still read; remove it when replaced by typed state. `reference_local_keys` may be temporary run-scoped scan storage; it is never a persistent authority. Remove unused tables/config keys. Do not keep the old global `baseline_revision` as the truth when records/scopes are at different revisions.

Add exact unique/index constraints for lookup/filtering, not foreign-key constraints. Correction history is append-versioned; the binding's current-correction pointer and row lock serialize the active version. Do not rely on dialect-specific partial unique indexes.

All related state and projection changes commit together. Review/export/run operational timestamps use appropriate `timestampTz` and immutable casts. Centralize status transition timestamps. Every table/model needs the same table resolver discipline as existing Addressing models.

## 7. Shared mutation and reconciliation contracts

Prefer small core Actions under existing `src/Actions`, with typed data contracts under `src/Data` and support code under `src/ReferenceSync`. Follow current plain Action conventions; do not add `lorisleiva/laravel-actions` or another dependency merely to get an Action name.

Names below specify responsibilities; preserve existing public names where their meanings remain accurate:

| Action/support responsibility | Inputs | Result |
| --- | --- | --- |
| Load/verify reference release | trusted local artifact path and optional expected revision | immutable verified candidate; no effective writes |
| Install canonical scopes | verified new-protocol release and explicit scope selection | newly installed projection, canonical ledger, and bindings in one validated installation flow |
| Record local reference correction | resolved identity/record, expected projection version/checksum, explicit field patch or operation, actor, reason/evidence | versioned correction plus validated effective write |
| Build reference update plan | installed scope, candidate release/generation, scope mutation token | paginated immutable candidates and dependency change sets |
| Review reference change | ID, expected fingerprint, decision, actor, rationale | decision against exactly that fingerprint |
| Apply approved change set | change-set ID, expected approved fingerprint, candidate generation | atomic domain writes and baseline advancement, or explicit block reason |
| Export approved contributions | frozen set of approved fingerprints | immutable private archive/history row |
| Inspect contributions | untrusted archive and optional report path | validated proposals only |
| Scan unmanaged drift | scope/run and cursor | discrepancies against expected projection checksums |

### Local correction transaction

1. Resolve the record and its portable binding; verify scope is installed by the new canonical installer and no conflicting maintenance operation owns it.
2. Lock binding and affected domain records in a stable order.
3. Verify submitted expected version/checksum. Reject stale forms.
4. Validate operation/field allowlist, actor, evidence format, references, and domain invariants.
5. Append a correction version; preserve the canonical before-values for fields first changed. Re-editing a correction does not replace its original canonical base with the previous override value.
6. Apply through the shared domain mutation operation.
7. Store normalized expected projection checksum, increment scope/binding version, supersede the prior correction, invalidate affected reviews/plans, and commit together.
8. Invalidate affected caches after commit. Filament-specific filter caches stay in the adapter; core emits a narrow event if adapter invalidation needs notification.

Local additions and external observations are explicit operations through this boundary too. Their required canonical parent scopes must exist, but a new observation has no fabricated canonical baseline. For example, OneMap observations use a declared external provenance scope, not a package scope declared complete by a release. Retain acquisition/source details internally; only an independently verified, explicitly reviewed public proposal may be exported. No missing-row retirement inference applies to external scopes.

### Field reconciliation table

For each explicitly corrected field: `B` is that correction's recorded canonical base, `L` its proposed local value, and `R` the incoming canonical value.

| Condition | Plan |
| --- | --- |
| No local correction | Incoming value is eligible after normal validation/review |
| `R == B` | Keep the local correction while adopting other compatible incoming values |
| `R == L` | Canonical has incorporated the value; clear only that matching correction field after successful apply |
| `R != B` and `R != L` | Conflict; preserve effective local value and require explicit resolution |
| Changes affect different fields | Combine eligible fields; still validate the whole record/change set |
| Required reference unavailable or blocked | Block dependent set; never substitute null |
| Scope absent/incomplete | Unknown/uninstalled; no deletion inference |

On conflict, do not advance that record's baseline or claim its scope fully installed. Keep the original correction base and verified candidate available. Other independent eligible sets may progress. Repeated runs must be idempotent.

Conflict choices: keep local intentionally, adopt canonical, or submit a revised correction. Each is a new explicit reviewed version/decision. Do not make a single ambiguous "approve conflict" button.

### Application transaction

1. Select eligible approved sets before limiting the batch; group dependencies, not arbitrary UUID order.
2. Verify approval fingerprint includes baseline checksum, correction version, effective checksum, incoming revision/digest, scope/schema/identity mapping, and the exact graph operations.
3. Lock involved bindings/rows in stable order, recheck all tokens, and confirm no unmanaged drift.
4. Resolve all required parents/endpoints before writing children. Absence is never converted to a nullable relation unless the release explicitly says the relation is absent and the domain permits it.
5. Validate the final projected graph: same-country constraints, cycles, level compatibility, state/area bridge rules, source ownership, and postcode-primary invariants.
6. Apply the complete set through existing/extracted domain rules. Preserve slugs when configured/customized and preserve physical IDs.
7. Advance baselines only for committed members; clear/rebase matching correction fields explicitly; update checksums, versions, lifecycle state, and decisions atomically.
8. A failure rolls back every member. Persist the block reason separately from the rolled-back transaction. Do not silently swallow exceptions into a generic deferred count.
9. Do not rescan all geography before and after each batch. Validate affected versions/checksums and recompute affected candidates only.

### Retirement

No automatic hard delete of shared core nodes. Where an existing active/inactive mechanism exists, use its documented deactivation semantics. Where it does not, retain a retired canonical marker and block destructive application until there is a concrete dependency/remap policy. No invented generic soft-delete layer.

Link removals require a reviewed complete graph set. Existing instance snapshots/free-text addresses stay unchanged. Any explicit replacement of live foreign references uses the existing cleanup/remapping contracts and includes a cross-tenant integrity test. Never bulk-delete referenced states/cities through query builder paths that bypass cleanup.

## 8. Clean installation; no compatibility or legacy path

The user explicitly requires no backward compatibility or legacy support. Treat this as a clean replacement of the prototype.

1. Edit the existing owning migrations to describe the final schema. Do not add one migration per alteration or detect/repair old schema at runtime.
2. Initialize only through a verified new-protocol package release. Install selected complete scopes, their dependencies, canonical baselines, durable bindings, and the effective projection.
3. Create the baseline from that release during installation, before local corrections are enabled. Remove the old workflow that seeds mutable rows and later tries to infer a pristine baseline.
4. A truly new scope must have no pre-existing unbound rows that collide with its install. Fail explicitly rather than adopting, reinterpreting, resetting, or overwriting existing data.
5. Interrupted installations resume from their persisted new-protocol run state and preserve their newly allocated physical IDs. This is normal retry behavior, not legacy conversion.
6. Keep existing public seeder/command names only where they remain the single genuine entry point with the new semantics. Do not add aliases for removed classes, methods, configuration keys, command options, tables, or archive versions.
7. Runtime installation always uses the canonical installer. Raw provider seeding is an internal isolated build operation, with an explicit build context that cannot be entered through ordinary application writes. The public seeder routes to installation on a new scope and to the reviewed update plan on an installed scope; no old live mutation path remains.
8. Do not implement historical-baseline adoption, old identity mapping, automatic data conversion, old-format receipt handling, legacy importers, dual read/write, deprecation shims, or an unmanaged editing mode.
9. Preserve the installed history created by the new system. Future revisions of the same new protocol reconcile against their recorded canonical baselines. Ordinary version history and idempotent retry remain required behavior.

Document the breaking change and clean installation requirements. Rebuilding an existing database is a separate operator action; the implementation agent must not perform it automatically or generate an upgrade/backfill subsystem.

Perform initial population before serving traffic for those scopes. Candidate release staging is invisible until verified; effective updates to an already-installed scope commit by coherent change set. Do not claim that a partially populated initial scope or a partially applied release is fully installed.

### Operator command contract

Keep command options small and route them to the same core Actions used by jobs/UI:

| Command | Required behavior |
| --- | --- |
| `address:reference-build {revision} --output=... --scope=...` | Isolated source build; repeatable scope selection; defaults build the explicit complete release profile |
| `address:reference-verify {manifest}` | Validate identities, schemas, digests, scope completeness and references without runtime application writes |
| `address:reference-install --scope=... --dry-run` | Install trusted packaged scopes into a clean scope; dry run returns the plan without mutation; existing completed scope directs operator to the update planner |
| `address:reference-plan --scope=...` | Load the trusted packaged candidate and create/update a versioned review plan; no projection writes |
| `address:reference-apply {run} --actor=...` | Apply eligible approved sets only; respect review fingerprints and blocked dependencies; CLI does not imply blanket approval |
| `address:reference-drift --scope=...` | Report unsupported out-of-band edits against recorded expected projections |
| `address:reference-inspect {archive} --report=...` | Inspect untrusted contributions and emit a review report; never apply them |

Scope options may be repeated. The installed package manifest is the default runtime trust source. A supplied manifest path to the verifier is not permission to install arbitrary uploaded data. Reject removed prototype options/versions; no compatibility aliases. Add command tests for malformed scopes, unverified artifacts, dry-run non-mutation and approval bypass attempts.

## 9. Entry-point integration inventory

Use graph searches and traces to confirm every actual caller before changing signatures. At minimum inspect and cover:

### Core package

- `SeedAddressingAction`, `SeedAddressCountriesAction`, `SeedAddressCountryReferencesAction`, `SeedAddressStatesAction`, `SeedAddressCitiesAction`, `SeedCountryGeographiesAction`, `SeedPostalCodesAction`.
- Bundled seeders and country/postcode seeding commands.
- `SaveAddressAreaAction`, `SaveAddressAreaAliasAction`, `DeleteAddressAreaAliasAction`, `SavePostalCodeAction`.
- `ImportAddressAreasAction`, `ImportPostalCodesAction`, `ImportPostalCodeAdminDataAction` and their commands/sources.
- Gap-to-alias promotion actions that create reference aliases.
- Runtime Singapore postcode resolution: record it as external/runtime provenance, not automatically canonical package truth.
- Model delete/cleanup hooks and bulk delete paths.
- `ModelResolver`, `AddressingTableResolver`, geography/profile resolvers and cache seams.

### Filament adapter

- `ReferenceSyncPage`, its Blade view, plugin registration and configuration.
- Country/state/city edit pages that currently perform ordinary model updates.
- Area/postcode create/edit pages and delete/bulk-delete handlers.
- Alias mutation handlers and any editable relationship/coverage handlers.
- `AddressAreaImporter` and `PostalCodeImporter`; use the existing built-in Filament import/export mechanisms.
- Any update via a relation manager; do not modify read-only managers unnecessarily.
- `AddressingFilterOptions` invalidation after completed writes.

Reference-edit pages require a correction reason and evidence and invoke the core correction action. They do not become an independent direct mutation path. A scope must first be installed by the new canonical installer. Do not preserve unmanaged editing, old save behavior, or a legacy management toggle as a fallback. Runtime/external and local-only records still use the same explicit mutation/provenance boundary.

Import jobs produce reviewed correction/change-set proposals; canonical refresh cannot quietly run under the label of ordinary CSV import. Existing importer dry runs and validation remain reusable.

### ReferenceSync replacements

| Current file | Disposition |
| --- | --- |
| `Canonical.php` | Retain useful canonical serialization; add explicit per-type normalization and byte-safe limits |
| `ReferenceTypes.php` | Replace denylist projection and mutable-key identity with explicit portable schemas and durable bindings |
| `SnapshotRepository.php` | Replace with release/scope/canonical storage responsibilities; remove prototype initialization/storage assumptions and global revision shortcuts |
| `DiffEngine.php` | Replace full-table rebuild-on-every-apply with scoped planning, correction comparison, dependency sets, and versioned candidates |
| `ReferenceReview.php` | Require expected fingerprints and immutable reviewed content; distinguish export review from downstream approval |
| `ReferenceWriter.php` | Replace duplicated domain writes with shared mutation/change-set application |
| `ReferenceArchive.php` | Versioned scoped release validation plus frozen contribution exports |
| `ReferenceSyncJob.php` | Bounded resumable run jobs; remove arbitrary string-dispatch behavior if typed jobs are clearer |
| Build/inspect commands | Keep command purposes; add new-protocol verification/installation/planning operator surfaces as specified below; no aliases for removed signatures |

Remove dead prototype branches when replaced. Do not leave compatibility aliases for schema v1 or keep unsafe direct writes behind a hidden toggle.

## 10. Filament review and export behavior

Use existing Filament conventions and core Actions. Fix UUID quoting immediately. Pagination uses supported framework mechanisms; forms and state changes are authorized and validated server-side.

The page presents:

1. Installed scopes/revisions, incoming verified release, completeness and installation state.
2. Local corrections, contributions, incoming plans, conflicts/blocked sets, and unmanaged drift.
3. Field-level original canonical, effective local, and incoming values; graph changes show affected parents/links/dependencies together.
4. Reason/evidence and local reviewer provenance; internal actor IDs never appear in portable contribution payloads.
5. Separate approve-for-export and approve-for-application controls with expected fingerprints.
6. Explicit conflict choices and clear reasons for blocked application.
7. Per-run queued/running/completed/failed state, progress/cursor and retry information.
8. Private immutable export history and authorized downloads.

Approval invalidates when any relevant value, correction version, release, mapping, schema, dependency, or effective checksum changes. Sorting/pagination/re-rendering alone does not invalidate approval.

Export must freeze the approved content transactionally, then stream that immutable content. Never export stale current rows merely because an earlier comparison had an approval. Subsequent edits create new candidates; an already-written archive remains an accurate record of its frozen approved proposal.

Contribution schema includes proposal/change-set identity, portable record identity or local proposal ID, scope, original canonical revision/checksum, before/after or explicit field patch, dependencies, evidence, and exact fingerprint. Manifest contains count/schema/digest. Export status means exported, never accepted or released.

Inspect untrusted archives without applying them. Validate allowed paths/members, compressed/uncompressed limits, manifest size before reading it wholesale, byte lengths, count, schemas, duplicate identities, reference structure, operation consistency, digest, and fingerprint. Checksums prove integrity, not trustworthiness. Treat note/evidence strings as data and escape output.

Maintainer intake remains human/agent reviewed source editing. Do not build a universal source-patch compiler or automatic PR creator. Source validation and package tests decide acceptance. Record acceptance fingerprints/identity mappings in the canonical release metadata; local application clears a correction only after verifying the released values actually incorporate it.

## 11. Jobs, consistency, and scale

- Use durable per-run records, not one global `job` settings value.
- Jobs process bounded chunks and persist resumable cursors. A restarted job cannot activate a partial scan or partial candidate release.
- Jobs carry explicit run/scope/release IDs and actor provenance. They never rely on web authentication or ambient tenant state.
- Acquire shared per-scope maintenance exclusion for planning/apply/installation and increment the scope mutation version on every supported write. Planning/drift scans use run-scoped staging and verify the start/end mutation token before activation. Invalidated scans retry or report stale state; they do not sweep active generations.
- Cache locks provide scheduling exclusion; locked database versions/fencing checks enforce final correctness. The database transaction must reject a worker whose run/generation token has been superseded.
- If a job cannot acquire its lock, release/reschedule it with bounded backoff and visible status. Do not exhaust it merely because another operation is healthy.
- Define `job timeout < lock lifetime` and `job timeout < queue retry_after/visibility timeout`, with margin. Pick bounded segment limits and document the required worker/connection configuration. Do not rely on the prototype's two-hour job.
- Mark run failure on worker timeout/final failure using the framework failure hook; stale-run recovery must not label a partial import as complete.
- Application and baseline advancement are idempotent by change-set identity/fingerprint. Retrying a committed set is a no-op.
- Stage candidate generations and switch active pointers only after complete validation. Clean up abandoned staging separately after it is no longer referenced.
- A normal apply batch performs no full-table scans. Full drift audits are explicit scoped operations. Stream/disk-sort large builds and avoid loading millions of checksums/records into PHP collections.
- Preserve completed run/export provenance; prune only unreferenced temporary generations according to a documented policy.

If actual supported database semantics differ, prove the transaction/exclusion behavior on that driver. SQLite unit tests alone cannot prove production concurrency safety.

## 12. Ordered implementation slices

Each slice has a focused diff, regression tests, documentation updates, and a clear completion condition. Use these as logical commit boundaries; follow the repository's Git approval rules before operations spanning multiple commits.

### Slice 0 — Capture failures and contain unsafe application

Tasks: promote the five regressions, add UUID rendering and job contention/expiry tests, keep existing feature opt-in, and add the independent apply permission. Disable/remove UI/application paths that cannot meet current safety invariants while replacing them. Do not make downstream capability appear available after only fixing buttons.

Done: all defects are represented by tests or explicit temporary unsupported-operation guards. Read-only comparison/export behavior remains usable where prerequisites exist.

### Slice 1 — Portable schema, scope contract, and identity catalog

Tasks: implement typed envelopes/scopes; per-type allowlists; explicit relationship references; canonical normalization; initial committed identities; explicit identity allocation/verification; rename/replacement rules. Define build input digest.

Done: a city/state/area rename keeps its portable ID, JSON ordering/timestamps do not create changes, personal/source payload fields cannot enter a bundle, and incomplete/uninstalled scopes never produce retirement candidates.

### Slice 2 — Typed storage and mutation boundary

Tasks: update the owning migration and resolver defaults; implement release/scope/binding/correction/run storage and lifecycle transitions; shared transactions/version checks; correct model/table generics and casts. Install an early shared guard that rejects raw seeding/import writes to an installed scope before they mutate anything. Keep that guard until the entry point is routed through the new planner. Check existing shared primitives before creating local helpers.

Done: a local scalar correction changes the effective row and correction history atomically; a failed save leaves neither half committed; stale writes fail; model/table remapping works.

### Slice 3 — Reproducible releases and distribution verification

Tasks: replace archive schema; clean isolated builds; explicit scope selection; missing/duplicate identity failures; deterministic output; manifest verification; PR verification workflow; addressing split guard; reject a managed DB as canonical build source.

Done: two clean builds match, a packaged/tagged tree includes artifacts, stale/missing artifacts fail CI, malformed releases do not change active candidate state, and installed app mutations never change a canonical build.

### Slice 4 — Clean canonical installation

Tasks: install new-protocol scopes with durable bindings, canonical baselines and effective rows; enforce exact scope completeness/dependencies; reject pre-existing unbound reference rows rather than guessing their origin; implement resumable/idempotent clean installation. Remove prototype-baseline setup and all adoption/conversion code.

Done: fresh full and partial-country installation work and can resume after interruption without duplicate rows. Existing prototype/populated unbound scopes receive an explicit unsupported-state error and no mutations. No historical matching, legacy schema branch, or backfill exists.

### Slice 5 — Controlled local corrections across supported entry points

Tasks: wire scalar save actions, state/city/country pages, aliases/postcodes, gap promotion, importer proposal paths, and unmanaged-drift checks. Audit deletes/bulk writes and runtime external-data provenance. Implement model-independent identity binding.

Done: every supported managed mutation records provenance through one boundary; direct out-of-band writes are detected and cannot be silently overwritten by a release.

### Slice 6 — Contribution review, immutable exports, maintainer intake

Tasks: fingerprinted review, field diffs/evidence, graph proposal display, immutable export snapshots, private downloads, archive inspection, acceptance metadata contract. Keep canonical application gated off.

Done: a production correction can be reviewed/exported, inspected in a maintainer checkout, represented as a source change, and linked to an accepted fingerprint in a subsequent fixture release. The Slice 2 guards already prevent reseeding/import bypasses from erasing captured corrections; a blocked operation explains which new entry point to use.

This is milestone A: a usable contribution lifecycle. It must not advertise fully safe downstream updates yet.

### Slice 7 — Field reconciliation and dependency-aware update plans

Tasks: implement the reconciliation table, explicit conflict resolutions, graph grouping, dependency ordering, plan generation/version tokens, eligible-before-limit selection, and targeted recomputation.

Done: all scalar combinations and missing/deferred dependencies classify deterministically; no starvation; disjoint field changes combine safely; a changed approval token blocks application.

### Slice 8 — Atomic domain application and safe retirement

Tasks: route approved sets through existing/extracted validators; preserve IDs/slugs; parent+edge atomicity; country/state consistency; postcode primaries; explicit identity replacement; conservative retirement; rollback and post-commit cache invalidation.

Done: all five original regression cases pass with the intended supported behavior; graph failure rolls back every member; no customer address/snapshot is rewritten incidentally; retry cannot double-apply.

### Slice 9 — Replace reseeding bypasses and harden resumable jobs

Tasks: managed seeder/import orchestration uses the canonical plan boundary; raw source building remains isolated; scope version/fencing, staged generations, lock contention rescheduling, failure hooks, queue timing, run progress, recovery and cancellation semantics. Do not add generic workflow machinery beyond these operations.

Done: running the ordinary public seeder on a managed installation preserves corrections and cannot bypass pending conflicts. Concurrent edit/scan/load/apply cases either serialize, retry, or reject explicitly without losing data.

### Slice 10 — Complete UI, acceptance mappings, docs and end-to-end tests

Tasks: enable apply only with the separate permission and all prerequisites; released acceptance mappings resolve local additions safely; update canonical package docs/contexts/config examples; remove dead prototype paths; complete round-trip and dialect/concurrency verification.

Done: milestone B acceptance matrix below passes. No claimed ability depends on a missing artifact or manual hidden database write.

### Slice 11 — Performance evidence and release readiness

Tasks: benchmark an actual supported large postcode/coverage dataset; capture rows, queries, time, peak memory, DB/storage size and queue segment durations; verify targeted application without whole-table rescans; validate release contents from the split package tree.

Done: documented measured behavior and worker settings, no memory proportional to whole-scope rows on ordinary apply, bounded resumable jobs, and a concrete rollback/recovery procedure. Leave production enablement/publication for the user's deployment decision.

## 13. Acceptance test matrix

Use Pest with parallel execution. Extend existing tests where they already own a domain rule; add narrowly named ReferenceSync suites for new contracts.

### Identity, normalization, artifacts

- Local database UUIDs differ between two installs; canonical IDs/digests match.
- Name/code/parent locator rename preserves canonical identity and physical row ID.
- Unknown/new/duplicated/reused IDs fail verification; explicit replacement mapping passes with dependency checks.
- Equivalent JSON key order, timestamp touches and generated slugs do not create proposals.
- Numeric, null and boolean round trips match on SQLite/MySQL/PostgreSQL.
- Missing relation is distinct from deliberately null relation.
- Duplicate archive records, oversized manifest/member/line, invalid digest/count/schema/kind/path and unknown fields are rejected before activation.
- Two clean builds have matching logical digests; pinned-environment archive bytes match.

### Installation, scope, provenance

- Fresh full and partial installs record exact scopes/dependencies.
- Disabled cities/postcodes/providers/villages produce no deletions.
- Runtime external postcodes/custom provider rows remain outside canonical deletion scope.
- Clean installation creates canonical bindings and baselines before allowing corrections.
- Pre-existing prototype rows, old archives and old schema shapes fail explicitly without conversion or partial mutation.
- Re-running the new installer is idempotent; it does not require a compatibility branch.
- New correction uses original canonical base; re-edit appends a version without losing that base.
- Explicit null patch, correction cancellation and local creation work.
- Raw model/query-builder drift is detected and blocks affected writes.

### Review and reconciliation

- All rows of the field reconciliation table, including disjoint fields.
- Stale rendered change ID plus changed fingerprint cannot approve replacement content.
- Stale form version cannot overwrite newer correction.
- Changing release/mapping/dependency invalidates relevant approval.
- Exported archive remains immutable after later local edits.
- Export approval and apply approval are distinct and checked separately.
- Conflict choices preserve reviewer rationale and effective values until resolved.
- Already-accepted correction disappears only after verified matching release application.
- Local proposal acceptance maps to canonical identity without duplicating the effective row or changing its UUID.

### Domain integrity

- Missing state/parent blocks child; it never becomes null by fallback.
- Self-parent, cycles, cross-country parents and invalid levels are rejected.
- Valid reparent moves the source-owned containment edge atomically and preserves unrelated manual edges.
- Exactly one postcode primary; secondary coverage survives compatible updates.
- A graph failure leaves every node/link/baseline/correction unchanged.
- Retirement retains live instance references or follows an explicit tested remap/cleanup policy.
- Existing `StateReferenceIntegrityTest`, area hierarchy, save/import and assignment tests stay green.
- Cross-tenant instance references remain isolated; deliberately global cleanup affects all relevant owners without exposing their data.

### Queues, UI, concurrency and scale

- UUID review/download expressions render correctly; authorization denied without the explicit gates.
- Tenant admin privileges alone grant no global management/application capability.
- A healthy lock holder causes rescheduling, not a lost request.
- Timeout/retry/failure hook records an accurate failed/resumable run.
- Stale worker/generation cannot activate a candidate or advance a baseline.
- Failed halfway import leaves old active candidate intact.
- Concurrent edit during plan creation invalidates the plan; concurrent edit during application serializes or fails the version check.
- Deferred first item does not starve later eligible sets.
- Retried committed set is a no-op.
- Apply of one small set does not perform full reference scans.
- Downloads never expose arbitrary filesystem paths; unauthorized users cannot retrieve exports.
- Public seeders cannot erase active corrections on managed scopes.
- Source build in an isolated environment cannot capture application owner/customer data.

### Mandatory end-to-end scenario

Use a tiny real provider for orchestration (Saint-Barthélemy is the existing suggested fixture) and a small postcode graph. Use Malaysia samples only when postal hierarchy behavior specifically needs them.

1. Build release A in a clean isolated database and install it into applications X and Y with different physical UUIDs.
2. X records a scalar correction and a local addition, with evidence.
3. Review/export X's proposals; inspect them in a maintainer fixture.
4. Represent accepted changes in source/catalog metadata, build release B, and include acceptance mappings.
5. Y has its own independent field correction. Load B; show disjoint merge and a conflicting-field variant separately.
6. Approve/apply compatible sets. X clears incorporated correction fields and resolves its addition without duplication. Y retains its independent local value.
7. Apply a valid graph move as one set; demonstrate missing-parent rejection and rollback.
8. Rerun normal seeding, reload B and retry application. No correction loss, duplicate row, stale approval, or extra application occurs.

## 14. Verification commands and reporting

Run the smallest affected tests first. Example commands below are known local entry points, not claims that future implementation tests already exist.

```bash
./vendor/bin/pest --parallel tests/src/Addressing/Actions/ReferenceSyncFeatureTest.php 2>&1 | tee /tmp/addressing-reference-tests.txt
./vendor/bin/pest --parallel tests/src/Addressing/Actions tests/src/Addressing/Support tests/src/Addressing/Models 2>&1 | tee /tmp/addressing-domain-tests.txt
./vendor/bin/pest --parallel tests/src/FilamentAddressing 2>&1 | tee /tmp/filament-addressing-tests.txt
./vendor/bin/pest --parallel tests/src/Addressing/Geography/GeographyProviderContractTest.php 2>&1 | tee /tmp/addressing-provider-contract.txt
./vendor/bin/phpstan analyse packages/addressing/src packages/filament-addressing/src --level=6 --no-progress 2>&1 | tee /tmp/addressing-phpstan.txt
```

Run new focused test directories too. Run provider seed/postal shard tests when seeder/provider behavior changes. Run the existing commerce-support architecture test if dependency direction or support boundaries change. Run scoped Pint on changed PHP files only. Do not run repository-wide formatting or an unrelated full test suite.

During the audit, PHPStan's parallel process listener was blocked by the sandbox; adding `--debug` allowed scoped analysis to complete. That is a local verification workaround, not a repository configuration change.

Inspect the final diff for forbidden FK/cascade patterns, raw tenant queries, unconfigured table names, unused config, static request state, unsupported entry-point bypasses and accidentally exported sensitive fields. Validate manifests from the actual packaged/split tree.

Final implementation report must state: completed slices, retained unsupported operations, changed public/config/archive behavior, tests/results, real-database concurrency coverage, benchmark evidence, artifact inclusion, clean-install instructions and explicitly documented breaking changes, and any deployment work still required. Passing static analysis does not substitute for the behavior matrix.

## 15. Suggested skills

- `laravel-best-practices`: mutation transactions, jobs, security and architecture; follow its required exploration workflow.
- `spatie-laravel-php`: PHP/Blade conventions.
- `pest-testing`: repository regression and integration tests; repository `--parallel` rule takes precedence over generic examples.
- `livewire-development`: review page state, stale forms, component tests and action behavior.
- `context7-mcp`: current installed-version framework/library API documentation when needed.
- `spatie-version-control`: branch/commit/PR conventions if the user authorizes those operations.

Do not activate unrelated integration skills or install packages. Existing plain Actions are the local convention; an optional skill must not introduce an unapproved dependency.

## 16. Implementation-agent kickoff

Read this plan and the referenced repository rules. Confirm the working tree and current HEAD, then briefly report any material drift from the reviewed commit. Implement the slices in order, with tests before fixes for the reproduced bugs. Preserve the source-of-truth model: canonical source/release, explicit corrections, existing effective projection, shared mutation boundary. Make routine implementation choices without reopening settled architecture. Ask only when current repository facts conflict with these requirements or a required external fact or user decision is genuinely missing. Do not reopen backward compatibility or propose historical adoption; the user explicitly excluded both. Do not deploy, publish, rewrite existing production data, or communicate externally as part of this handoff.
