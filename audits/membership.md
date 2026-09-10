# Membership Audit — DONE (2026-09-11)

## Verdict

The standalone membership package is disposition-complete. Every rated
finding M1–M3 and Q1 is implemented and verified. No rated findings remain
open.

## What was done

- **M1 — IMPLEMENTED.** Owner configuration is now top-level at
  `packages/membership/config/membership.php:37-43`; both owner-scoped models
  use `membership.owner` at
  `packages/membership/src/Models/MembershipApplication.php:52` and
  `packages/membership/src/Models/MembershipInvitation.php:57`.
  `tests/src/Membership/Unit/InstallationTest.php:42-50` verifies the new
  configuration path. A repo-scoped search found no literal former-key
  references in live packages or tests; stale generated evidence remains at
  `evidence/rule-violations.txt:833,837`.
- **M2 — IMPLEMENTED.** `MakePivotCommand` and its stub are deleted, and the
  provider registers only `SyncRolesCommand`
  (`packages/membership/src/MembershipServiceProvider.php:28-31`). The
  host-extensible `MembershipPivot` base is present at
  `packages/membership/src/Models/MembershipPivot.php:9-20`, with the host
  migration and subclass recipe documented at
  `packages/membership/docs/04-usage.md:21-72`. The removed command is
  asserted absent by
  `tests/src/Membership/Unit/MembershipCommandsTest.php:53-54`.
- **M3 — IMPLEMENTED.** Membership owns mapped-role assignment while authz
  owns role and permission APIs; the additive default, explicit `--prune`,
  unrelated-role preservation, and current-team-only revocation are documented
  at `packages/membership/src/Services/MembershipRoleSyncService.php:17-31`.
  Permission reconciliation is implemented at `:80-84`, team-context
  validation and restoration at `:136-151`, and the command exposes the
  explicit prune mode at
  `packages/membership/src/Console/Commands/SyncRolesCommand.php:13-23`.
  Member mutations keep pivot and authz changes in one transaction in
  `packages/membership/src/Actions/AddMemberAction.php:53-84` and
  `packages/membership/src/Actions/RemoveMemberAction.php:34-45`, with role
  changes delegating through `ChangeMemberRoleAction.php:37`.
- **Q1 — IMPLEMENTED.** `HasMembers` documents and implements transactional
  cancellation of pending applications and revocation of pending invitations
  while preserving terminal history at
  `packages/membership/src/Traits/HasMembers.php:30-64`. Coverage verifies
  terminal-history, pivot/user/role preservation, and cross-owner lifecycle
  cleanup at `tests/src/Membership/Unit/HasMembersTraitTest.php:86-181`.
- **Supporting lifecycle/security checks — VERIFIED.** Invitation status is
  enum-cast and transitioned through the model at
  `packages/membership/src/Models/MembershipInvitation.php:74-83,111-190`;
  bearer tokens are hashed and compared with SHA-256 at `:86-105,253-266`.
  Acceptance locks and owner-revalidates the invitation at
  `packages/membership/src/Actions/AcceptInvitationAction.php:27-63`, with
  replay coverage at `tests/src/Membership/Unit/AcceptInvitationActionTest.php:67-79`.

## Verification

- Membership Area: `./vendor/bin/pest --parallel tests/src/Membership` —
  **127 passed, 248 assertions, 8 processes**.
- Targeted checks: Commands **3 passed, 12 assertions**; role sync **9 passed,
  17 assertions**; actions **9 passed, 20 assertions**; `HasMembers` **7
  passed, 20 assertions**; invitation acceptance **10 passed, 22 assertions**;
  installation **7 passed, 11 assertions**; owner contracts **11 passed, 21
  assertions each**; owner isolation **2 passed, 7 assertions**.
- Pint — **passed**.
- PHPStan package source — **no errors**.
- `git diff --check` — **passed**.
- Membership migrations contain no forbidden foreign-key constraints or
  cascades.

## Audit deviations

- No one-release compatibility fallback was retained for the owner-key move;
  the no-backward-compatibility rule requires the former path to be removed.
  The package documentation confirms that only `membership.owner` is read at
  `packages/membership/docs/03-configuration.md:60-64`.
- The original M2 wording described model-file code generation; the verified
  replacement is deletion of the command/stub plus a documented host migration
  and pivot-subclass recipe. No generated host files are modified by the
  package.
- The old audit and generated evidence contained stale pre-fix references;
  `evidence/rule-violations.txt` was outside the implementation surface and
  remains untouched.
- Moderation’s parallel owner-key change is outside this package’s scope and
  remains a separate stream.
- No package migration was required. The host pivot migration in the usage
  documentation is an integration recipe, not a new membership migration.

## Residual notes

No unrecorded rated finding remains. Request-level invitation token lookup is
the caller’s responsibility, as documented at
`packages/membership/docs/04-usage.md:95-100`; the package action accepts an
already resolved, owner-guarded invitation. Re-open the audit if that boundary
or the membership/authz role ownership policy changes.
