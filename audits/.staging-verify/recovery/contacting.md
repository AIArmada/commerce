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