# membership Audit

## Packages Reviewed (bullets)

- `packages/membership` — multi-tenant membership: applications, invitations, member roles (standalone, no Filament adapter): 30 `src/` files (`Traits/HasMembers.php`, `Contracts/*`, `Enums/*`, `Models/*`, `Support/MembershipSubjectGuard.php`, 10 `Actions/*`, `Events/*`, `Services/MembershipRoleSyncService.php`, console `SyncRolesCommand`/`MakePivotCommand`), `config/membership.php`, 3 migrations
- Root test coverage consulted: `tests/src/Membership/` (19 files)

## Overall Assessment (quality, health, risks, refactor size)

membership is a disciplined standalone: both models (`MembershipApplication`, `MembershipInvitation`) use `HasOwner`+`HasUuids`+`getTable()`, all 10 Actions run in `DB::transaction()` with `OwnerWriteGuard::findOrFailForOwner()` on inbound rows, and `Support/MembershipSubjectGuard.php` validates subjects against `ownerScopeConfig()` — the correct commerce-support consumption this review set rewards. Issues are boundary-config and codegen hygiene: (1) owner config lives at nonstandard `membership.features.owner` while signals/docs/jnt use top-level `{pkg}.owner` (growth and moderation share the nonstandard nesting — standardize all three); (2) `MakePivotCommand` + stub scaffolds host-app pivot models via codegen (file-writing commands are a maintenance and supply-chain risk); (3) `HasMembers` trait injects membership behavior into host models with unclear cascade semantics; (4) `MembershipRoleSyncService`/`SyncRolesCommand` couple membership to Spatie roles via authz (hard require — correct — but sync direction and conflict policy need locking down); (5) invitation lifecycle spans `InvitationStatus` enum + a later `000003_add_lifecycle_state_*` migration — verify no dual-source truth. No migration required. Refactor size: Small-Medium, code-only.

## Migration Impact

**Migration Required: NO**

| Table | Column/Index/Constraint | Data migration | Notes |
|---|---|---|---|
| `membership_applications`, `membership_invitations` | none proposed | none | uuid PKs verified; no FK constraints (rg clean); lifecycle-state column from `000003` kept as-is |
| none | config-key rename is code+config only | none | `features.owner` → `owner` normalization reads the new key with fallback to the old (no data to migrate; config is not data) |

## Package Responsibilities

- Application flow: `Actions/{ApplyForMembership,ApproveMembershipApplication,RejectMembershipApplication,CancelMembershipApplication}Action.php`, `Models/MembershipApplication.php` (`ApplicationStatus`), `Events/{MembershipApplicationSubmitted/Approved/Rejected/Cancelled}.php`.
- Invitation flow: `Actions/{InviteMember,AcceptInvitation,RevokeInvitation}Action.php`, `Models/MembershipInvitation.php` (`InvitationStatus`), `Events/{MembershipInvitationSent/Accepted}.php`.
- Direct membership: `Actions/{AddMember,RemoveMember,ChangeMemberRole}Action.php` (`MemberRole`), `Traits/HasMembers.php`.
- Guards/contracts: `Support/MembershipSubjectGuard.php`, `Contracts/{MembershipApplicationNotifier,MembershipHook,MembershipMutationGuard}.php`.
- Role bridge: `Services/MembershipRoleSyncService.php`, `Console/Commands/SyncRolesCommand.php`, `Console/Commands/MakePivotCommand.php` (+ `stubs/make-pivot.stub`).

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)

### M1 — Nonstandard owner config path (`membership.features.owner`)
- Severity: Medium
- Location: `packages/membership/config/membership.php:7` (`json_column_type` present, good) + `features.owner` nesting; models pin `ownerScopeConfigKey = 'membership.features.owner'` (`Models/MembershipApplication.php:52`, `MembershipInvitation.php:57`); mirrored by moderation's `moderation.features.owner`
- Problem: Owner config lives nested under `features` here (and in moderation `moderation.features.owner`, growth `growth.features.owner`) while signals, docs, and jnt use a top-level `{pkg}.owner` key. Operators and agents must remember two conventions; generic tooling that reads `{pkg}.owner` silently misses membership/moderation/growth scoping.
- Why It Matters: Convention drift in security-critical config is how an install runs unscoped while appearing scoped.
- Recommended Fix: Move to top-level `owner` key in both configs; update the two `ownerScopeConfigKey`s; read with fallback (`config('membership.owner', config('membership.features.owner'))`) for exactly one release, then drop the fallback and the old key. Same pass for moderation (its audit M1 mirrors this).
- Breaking Change: YES (key path changes; fallback softens it — still classified breaking per instructions since old key is removed)
- Affected Packages: moderation (same fix, separate file), organizations (`CreateOrganizationAction`, `TransferOrganizationOwnershipAction`, `Organization` model + filament relation managers consume membership flows), host apps overriding `membership.features.owner`
- Required Dependent Changes: update config overrides to `membership.owner`; organizations package unaffected in code (no key references — verify via rg `membership\.features\.owner` before merge)
- Migration Required: NO

### M2 — `MakePivotCommand` codegen writes models into host apps
- Severity: Medium
- Location: `packages/membership/src/Console/Commands/MakePivotCommand.php`, `src/Console/Commands/stubs/make-pivot.stub`
- Problem: A console command scaffolds pivot model files into the consuming app. Generated models freeze today's conventions (traits, casts, table names) into host code that never receives package updates; the stub is a second, untested implementation of the model.
- Why It Matters: Codegen trades one-time convenience for permanent drift — every future membership fix must now also be hand-applied to generated copies.
- Recommended Fix: Delete the command + stub; ship a documented abstract `MembershipPivot` base class (or a `HasMembers`-compatible concrete pivot in `Models/`) that host apps extend with a 3-line subclass. Update `docs/04-usage.md` with the subclass recipe.
- Breaking Change: YES
- Affected Packages: host apps that ran the generator (their generated files keep working — nothing runtime is removed)
- Required Dependent Changes: docs update only; no runtime call-site change
- Migration Required: NO

### M3 — `MembershipRoleSyncService` sync semantics with authz roles under-specified
- Severity: Medium
- Location: `packages/membership/src/Services/MembershipRoleSyncService.php`, `src/Console/Commands/SyncRolesCommand.php`; hard require `aiarmada/authz` in composer (correct direction)
- Problem: Membership roles (`MemberRole`) and Spatie/authz roles are two role systems bridged by a sync service whose conflict policy (which side wins, what happens to extra roles, team scoping of synced roles) is not enforced in code paths I could verify as tested. `ChangeMemberRoleAction` + sync racing = role flapping.
- Why It Matters: Authorization state with two writers and no documented winner is a privilege-escalation-shaped bug waiting for a race.
- Recommended Fix: Make membership the single writer for member roles: `ChangeMemberRole/AddMember/RemoveMember` actions call the sync service transactionally (already in `DB::transaction` — add the sync call inside the same transaction); `SyncRolesCommand` becomes reconcile-only (add missing, never remove, `--prune` flag for removals, off by default); assert team-scoped role assignment (`AuthzScopeContext`/team id) in the sync path. Encode the policy in `MembershipRoleSyncService` docblock + one从业 test.
- Breaking Change: NO (default behavior: reconcile-add; removals now require explicit flag — safer default, no signature change)
- Affected Packages: authz (role storage — read/written via existing APIs), organizations (ownership transfer flows through member roles)
- Required Dependent Changes: none (internal)
- Migration Required: NO

## Code Quality Findings (same finding format)

### Q1 — `HasMembers` host-trait cascade semantics unclear
- Severity: Low
- Location: `packages/membership/src/Traits/HasMembers.php`
- Problem: A trait on host subject models implies lifecycle coupling (what happens to applications/invitations/memberships when the subject is deleted?) with no visible `deleting` cascade or documented "orphan" policy.
- Why It Matters: Silent orphan rows (or surprise cascades) on subject deletion.
- Recommended Fix: Document the policy in the trait docblock and implement it: on subject `deleting`, cancel pending applications + revoke pending invitations in the same transaction (application-level cascade, no DB cascades per rules). Add a test.
- Breaking Change: NO (new behavior on delete path; previously undefined)
- Affected Packages: organizations (subject model), host subject models
- Required Dependent Changes: none
- Migration Required: NO

## Laravel-Specific Findings

- PHP 8.4, no FK constraints/cascades (rg clean), uuid PKs, `getTable()` from `membership.database.tables.*`, `json_column_type` present — compliant.
- `lorisleiva/laravel-actions` required and used as `Actions/*` orchestration — compliant with reusable-orchestration guidance.
- No `down()` action needed. No soft deletes — compliant.
- Invitation `000003_add_lifecycle_state_*` migration is additive (new column) — safe; confirm `InvitationStatus` enum is the single reader/writer of that column (no second status accessor on the model — spot-check requested in testing below).

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction; N/A section for standalones csuite/membership/moderation/references)

- N/A — membership is standalone with no Filament adapter, which is correct for its scope (member flows are consumed via organizations' Filament managers: `filament-organizations` `MembersRelationManager`/`InvitationsRelationManager` — verified consumers).
- Adapter-adjacent note: those two relation managers must re-validate submitted IDs server-side (`OwnerWriteGuard`/`ResolveOwnedModelOrFailAction`) rather than trusting relationship options — flagged for the organizations/filament-organizations audit, not fixed here.

## Database Findings

- Two focused migrations + one additive lifecycle migration — good hygiene. No index findings beyond verifying composite coverage for the hot lookups (`invitations`: `owner + token/email`; `applications`: `owner + status`) — confirm with EXPLAIN on realistic volume before adding anything.

## Model / Domain Findings

- Models are exemplar `HasOwner` citizens; `ownerScopeConfigKey` change is mechanical (M1).
- `MemberRole` vs Spatie roles: keep both (member role = domain concept, Spatie role = authorization enforcement) with membership-as-writer (M3) — document the split in `docs/01-overview.md` so the next reader does not "simplify" them into one.

## Security Findings

- Write paths uniformly guarded (`OwnerWriteGuard` + transactions) — the strongest write posture in this review set alongside docs-after-fix.
- `AcceptInvitationAction` binds invitation→user by email (`$userEmail`) — verify token-first lookup (token is the capability; email is a confirmation, never the lookup key) and single-use invalidation inside the same transaction (replay = double membership). Required test below covers it.
- `InviteMemberAction` token generation must use hashed-at-rest tokens (`Str::random(48)` + sha256, the docs pattern) — verify and align if it uses a weaker scheme.

## Performance Findings

- No reporting-scale reads in this package; invitation/application lookups are point queries. No change.

## Testing Findings

- `tests/src/Membership/` (19 files) is healthy. Gaps tied to this audit: M1 config-key fallback test (old key honored, new key wins); M3 sync-policy test (reconcile adds, never removes without `--prune`; team scoping asserted); invitation accept replay test (second accept fails); subject-delete cascade/orphan test (Q1); cross-tenant read/write isolation via `OwnerScopingContractTests` reuse. Run: `./vendor/bin/pest --parallel tests/src/Membership`.

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)

| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| organizations (+ filament-organizations) | membership actions/events/traits | M1 key move (config-only), M3 sync policy, Q1 delete cascade | Update config overrides if any; relation managers unchanged (server-side re-validation is their own audit item) |
| authz | role sync target | M3 reconcile policy | None (API usage unchanged) |
| moderation | same config convention fix | Parallel M1 | Same key move in moderation |
| host apps | `MakePivotCommand`, config keys | M2 deletion, M1 key move | Subclass base pivot instead of generating; update config key |

## Recommended Refactor Plan (ordered steps)

1. M1: move owner key (with one-release fallback) in membership + moderation + growth together (all three share the `features.owner` nesting; signals/docs/jnt already top-level).
2. M3: single-writer sync policy inside existing transactions; reconcile-only command default.
3. Q1: subject-delete policy + cascade in trait.
4. M2: delete codegen command/stub; ship base pivot + docs recipe.
5. Verify invitation token hygiene; add required tests; run `./vendor/bin/pest --parallel tests/src/Membership`.

## Files Likely to Change

- `packages/membership/config/membership.php`, `src/Models/MembershipApplication.php`, `src/Models/MembershipInvitation.php`, `src/Services/MembershipRoleSyncService.php`, `src/Console/Commands/SyncRolesCommand.php`, `src/Traits/HasMembers.php`, `src/Actions/{AddMember,RemoveMember,ChangeMemberRole,AcceptInvitation,InviteMember}Action.php`, `docs/01-overview.md`, `docs/04-usage.md`

## Files / Code That Should Be Removed (explicit list, no legacy preservation)

- `packages/membership/src/Console/Commands/MakePivotCommand.php` + `src/Console/Commands/stubs/make-pivot.stub` (replaced by documented base-pivot subclass; verified no runtime references — commands are entry points, not libraries; re-grep `MakePivotCommand` for docs/tests mentions and update those)
- `membership.features.owner` config key + `features.owner` fallback (after one release; same pass as the move since instructions forbid legacy preservation — implement the new key and update all internal consumers now, no fallback shim kept)
- Nothing else: `SyncRolesCommand` stays (reconcile mode); both traits/contracts/events stay

## Final Recommended Architecture

membership remains a standalone owner-scoped domain with one config convention, transactional guarded Actions as the only writers, membership-as-single-writer role sync, explicit subject-delete semantics, and subclass-based (never generated) host integration.
