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
