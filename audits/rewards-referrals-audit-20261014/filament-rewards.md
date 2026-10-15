# Filament Rewards Adapter Audit (read-only) — 2026-10-14

Scope: `packages/filament-rewards` only (adapter). Core `packages/rewards` read as contract reference.
Read first: `CONTEXT-MAP.md`, `packages/filament-rewards/CONTEXT.md`, docs 01/03/04/99, `.ai/rules/index.md`,
all 42 PHP files under `packages/filament-rewards/src`, tests `tests/src/FilamentRewards/*.php`,
core contracts `PreviewRewardQualificationAction`, `CreateRewardProgramAction`, `RewardProgramGuard`,
`SettleRewardRecoveryAction`, `WriteOffRewardRecoveryAction`.

Severity: critical = cross-tenant mutation/disclosure, money/points mint/spend, authorization bypass;
high = deterministic broken core flow or write bypassing the package guard.

## Findings

### FR-1 HIGH — Qualification preview always fails (Carbon/enum vs string contract) — CLAIM VERIFIED
- Adapter: `packages/filament-rewards/src/Resources/RewardProgramResource/Pages/ViewRewardProgram.php:347-348`
  passes `'occurred_at' => CarbonImmutable::now('UTC')->subDay()` and
  `'verification' => RewardVerificationLevel::from(...)` (enum object).
- Core: `packages/rewards/src/Actions/PreviewRewardQualificationAction.php:242-245` requires
  `is_string($rawOccurredAt)` matching an absolute-timestamp regex, else throws
  `InvalidArgumentException`. Line 254 then does `(string) $input['verification']`, which would
  error on a backed enum if reached. The `occurred_at` check fires first.
- Failure scenario: staff opens a program, runs Preview qualification with any inputs; the handler
  (`ViewRewardProgram.php:357-359`) converts the exception into a validation error pinned to
  `component`. The documented "qualification preview sandbox" (`docs/04-usage.md:9`) never returns
  a decision. No persisted state changes (read-only), so high, not critical.
- Minimal fix: pass strings — `'occurred_at' => CarbonImmutable::now('UTC')->subDay()->toIso8601String()`
  and `'verification' => (string) $data['verification']` — or build a `RewardPreviewFixture` object
  directly (its constructor takes `CarbonImmutable` + enum, `packages/rewards/src/Support/RewardPreviewFixture.php:28-35`).
- Regression test: Livewire `callAction('preview_qualification', ...)` on `ViewRewardProgram` as staff
  asserts `assertHasNoActionErrors()` and `assertNotified('Preview: ...')`. No such test exists today
  (`grep preview_qualification tests/src/FilamentRewards` is empty).

### FR-2 MEDIUM — Preview has no member/beneficiary path (misleading sandbox) — CLAIM VERIFIED
- Adapter call at `ViewRewardProgram.php:340-356` passes only program/component/fixture/decisionTime.
  Core `handle()` accepts `?array $member = null` (`PreviewRewardQualificationAction.php:53`) and only
  loads enrollment/period/acceptance snapshots when a member is supplied (lines 122-179). The form
  (lines 320-334) offers component/outcome/verification/toggles but no member picker, and the preview
  API has no `RewardBeneficiaryRole` input at all (live evaluation requires one; see test helper
  `EvaluateRewardEntitlementAction` calls in `RewardAdminAdaptTest.php`).
- Failure scenario: after FR-1 is fixed, every program published with the default
  `require_enrollment=true` (`ViewRewardProgram.php:208`) previews as not-enrolled reject/hold,
  regardless of toggles; referrer-vs-recipient two-sided rules cannot be previewed either. Staff
  cannot distinguish "rule wrong" from "sandbox has no member".
- Minimal fix: add optional member type/id fields (defaulting to the synthetic fixture) and pass
  `['member_type' => ..., 'member_id' => ...]`; label member-less previews as unenrolled fixtures.
  Beneficiary-role support needs a core preview API change — file against `rewards`, not the adapter.
- Regression test: enroll a member, preview with that member under `require_enrollment` rules, assert
  decision differs from the member-less preview.

### FR-3 HIGH — Payee list is not staff-gated despite docs — CLAIM VERIFIED
- `packages/filament-rewards/src/Resources/RewardPayeeResource/RewardPayeeResource.php:17-48` defines
  no `canViewAny()`, no `getEloquentQuery()`; `Pages/ListRewardPayees.php:20` is a bare `ListRecords`;
  `Tables/RewardPayeesTable.php:24-99` adds no query constraint. Contrast
  `RewardFraudResource.php:40-45`, which implements `canViewAny()` with `can('reward-fraud-steward')`.
- Docs promise the opposite: `docs/99-troubleshooting.md:13` "The queue is staff-gated instead of
  program-scoped" and `CONTEXT.md:34` "(recipient-keyed, staff-gated)".
- Failure scenario: any authenticated panel user without staff roles (role-aware host) opens Payees and
  reads every recipient's email (via `RewardAdminPresenter::recipientLabel`, `RewardPayeesTable.php:31`),
  payout method, masked account display, and verification status across all programs/tenants. The
  verify/reject record actions and the register header action do re-check `staffActor()`
  (`RewardPayeesTable.php:56,87`; `ListRewardPayees.php:50`), so this is disclosure, not mutation.
- Minimal fix: add `canViewAny(): bool => RewardAdminPresenter::staffActor() !== null` to
  `RewardPayeeResource` (mirroring the fraud pattern), or an explicit `can('...')` ability.
- Regression test: non-staff panel user gets 403 / cannot see `ListRewardPayees` records; staff can.

### FR-4 CRITICAL — Program creation has no staff gate — CLAIM VERIFIED
- `packages/filament-rewards/src/Resources/RewardProgramResource/Pages/CreateRewardProgram.php:23-41`
  runs `CreateRewardProgramAction` with no `staffActor()` check; `RewardProgramResource.php` has no
  `canCreate()`/`canViewAny()`; `ListRewardPrograms.php` is bare. Every other write surface in the
  package aborts on `staffActor() === null` inside its handler; create is the exception.
- Failure scenario: any panel user (including a non-staff operator account on a role-aware host)
  creates arbitrary reward program families, including `platform` (global, null/null owner) programs
  visible in every owner scope (`RewardProgramGuard` resolves with `includeGlobal: true`). Programs
  are born as drafts, but creation squats the unique `family_key` and pollutes every tenant's program
  list; combined with FR-5 it targets a chosen tenant. Authorization bypass + cross-tenant mutation.
- Minimal fix: `abort_unless(RewardAdminPresenter::staffActor() !== null, 403)` at the top of
  `handleRecordCreation()`, plus `canCreate()` on the resource for UI consistency.
- Regression test: non-staff `Livewire::test(CreateRewardProgram::class)->fillForm(...)->call('create')`
  asserts forbidden and zero `RewardProgram` rows created.

### FR-5 HIGH — Create trusts owner alias/UUID without resolving a configured, existing owner — CLAIM VERIFIED (with nuance)
- Adapter: `CreateRewardProgram.php:26-35` forwards the submitted `owner_type` alias string and
  `owner_record_id` verbatim. Form options/search are config-driven
  (`RewardProgramForm.php:66-118`), but nothing server-side re-checks the submitted alias against
  `filament-rewards.owners.types` or confirms the record exists.
- Core nuance: `CreateRewardProgramAction::resolveOwner()` does validate that the type resolves to a
  Model morph and the id is a UUID — so arbitrary garbage fails — but it never checks the owner row
  exists and never consults the adapter allowlist. Any resolvable morph (e.g. `user`) plus any UUID
  is accepted and stored verbatim as `owner_type`.
- Failure scenario: a tampered Livewire payload creates a program owned by a victim tenant's UUID
  (appears in the victim's scoped lists) or by a nonexistent owner (orphan program invisible to its
  creator under owner scope). The alias is also stored literally (`organization`), so hosts whose
  morph map uses FQCNs get rows `HasOwner` scoping cannot match.
- Minimal fix: in `handleRecordCreation()`, resolve the alias through `filament-rewards.owners.types`,
  load the owner model with `whereKey(...)->firstOrFail()`-style validation, and pass the Model
  instance (core accepts `Model|string|null` and derives `getMorphClass()`).
- Regression test: submit create with an unconfigured alias and with a valid alias + random UUID;
  both assert form errors and no row created.

### FR-6 CRITICAL — Recovery settle/write-off never revalidate owner scope — NEW
- Adapter handlers (`RewardRecoveriesTable.php:53-96`) check `staffActor()` then hand the bound
  `$record` to core. Core `SettleRewardRecoveryAction.php:66-75` and
  `WriteOffRewardRecoveryAction.php:46-53` lock the recovery by key with no `RewardProgramGuard`
  call — the guard has `findProgram/Award/PayoutRequest/Grant/Item/AppealOrFail` but no recovery
  resolver. Every other program-bound core action used by this adapter calls the guard (verified by
  grep across all 25 actions; only payee verify/reject and funding-source are also guard-free, both
  intentionally recipient/global keyed).
- Docs promise otherwise: `CONTEXT.md:35` "Action handlers call guarded core actions";
  `docs/99-troubleshooting.md:21` "Record actions re-validate through the core write guards."
- Failure scenario: table query is scoped (`RewardRecoveryResource: applyViaAward`), but if the owner
  context switches between render and action, or the Livewire action is invoked with a foreign
  recovery key, settle appends a budget-recover movement that replenishes the victim program's
  available budget (money mint path) and closes the receivable; write-off silently forgives it.
- Minimal fix: add `RewardProgramGuard::findRecoveryOrFail()` (recovery -> award -> program) in
  `rewards` and call it in both actions before locking; adapter needs no change beyond the test.
  Flagged here because the adapter's documented contract depends on it; fix lands in core.
- Regression test: create recovery under owner A, invoke settle table action under owner B with A's
  key, assert refusal and no budget movement.

### FR-7 HIGH — Gate abilities are visible()-only for award/appeal decisions — NEW
- `RewardAwardsTable.php:65-66,78-79,93-94,107-108` gates approve/reject/reverse/dismiss_fraud on
  `RewardAdminPresenter::canDecideAward($record, 'approve'|'review'|'reverse'|'dismissFraud')`
  (`RewardAdminPresenter.php:232-241`, `Gate::forUser(...)->allows(...)`), and
  `RewardAppealsTable.php:66-67` gates decide likewise. Handlers (`RewardAwardsTable.php:160-177`,
  `RewardAppealsTable.php:68-85`) re-check only `staffActor()`, never the Gate ability. The fraud
  table dismiss (`RewardFraudTable.php:57-58`) similarly checks `can('reward-fraud-steward')` only
  in `visible()` (resource `canViewAny` still blocks page access there).
- Failure scenario: on a host that defines policies to separate approvers from general staff, any
  staffActor can invoke the table action directly (Livewire `callTableAction`) and approve/reverse
  awards or decide appeals the UI hides from them. `visible()` is not authorization (CONTEXT-MAP §5).
- Minimal fix: re-check `canDecideAward()` (and `can('reward-fraud-steward')`) inside each handler
  with `abort_unless(..., 403)` before calling core.
- Regression test: staff user denied by policy (`Gate::define('approve', fn () => false)`) calling
  the approve table action asserts forbidden and unchanged award status. Note existing tests use
  `Gate::before(fn () => true)` (`RewardAdminAdaptTest.php:59`), masking this.

### FR-8 LOW — Preview handler lacks the in-handler staff abort its siblings have — NEW
- `ViewRewardProgram.php:316` hides preview behind `staffActor() !== null`, but the handler at
  line 335 never aborts (publish line 68 and transition line 298 do). Read-only and the program is
  route-bound under `OwnerScope`, so impact is limited to non-staff rule probing on visible programs.
- Minimal fix: add `abort_unless(RewardAdminPresenter::staffActor() !== null, 403)` for symmetry.
- Regression test: non-staff `callAction('preview_qualification')` asserts forbidden.

## Verified contracts (false positives / OK)

- staffActor fallback matches docs: `RewardAdminPresenter.php:204-219` returns any authenticated user
  when the user model lacks `hasAnyRole()`, exactly as documented in `docs/03-configuration.md:37`
  ("Panel users without role support count as staff"). This is a documented panel-boundary decision,
  not an undocumented fail-open; residual risk only if a host puts non-operators in the same panel
  without role support. Not scored severe. (`staffRole()` returns `admin` only for `super_admin`,
  else `staff`; core actions independently require staff/admin actor roles.)
- Navigation: all 10 resources implement `getNavigationGroup()`/`getNavigationSort()` reading
  `filament-rewards.navigation.*`; `rg 'static.*\$navigationGroup' packages/filament-rewards/src` is empty.
- No direct ledger writes: `rg '::create|->save\(|DB::|->update\(|->delete\(|forceFill' packages/filament-rewards/src`
  is empty; all 30 action handlers delegate to core actions. Column-state closures and owner search
  are reads only.
- Owner-safe list queries: 8/10 resources scope via `RewardFilamentScope` through the program boundary
  (`getEloquentQuery()` in Award, Appeal, Payout, Recovery, Funding, Fraud, BenefitItem, BenefitGrant);
  `RewardProgram` relies on `HasOwner`/`OwnerScope` directly (`packages/rewards/src/Models/RewardProgram.php:58-62`).
  Payee is the sole exception (FR-3). Funding and benefit-item `program_id` selects render options from
  owner-scoped `RewardProgram::query()` and submitted ids are revalidated by core
  `RewardProgramGuard` in `RecordRewardFundingAction` / `CreateRewardBenefitItemAction`.
- Fraud queue: `canViewAny()` + program scope + held/pending filter (`RewardFraudResource.php:40-52`);
  dismissal requires a separate approval by design.

## Test gaps noted
- No tests for: preview action, program create page, non-staff denial on any surface, payee list
  gating, recovery cross-owner invocation. Existing suites cover staff happy paths, navigation config,
  award/fraud query scoping, and owner-scoped award visibility.
