# Can `aiarmada/membership` replace `AffiliateProgramMembership`?

**Verdict: No.** Same word, different domain. `aiarmada/membership` is a team-access
system (who can administer what, with which role). `AffiliateProgramMembership`
is a commercial enrollment record (which affiliate sells under which program, at
which tier, under what terms). Forcing the swap would delete affiliate-domain
behavior and drag access-control machinery into the engine for no benefit.

All claims below are sourced from package code, not from secondary write-ups.

## What `aiarmada/membership` is

Source: `packages/membership/{CONTEXT.md,composer.json,docs/01-overview.md,src/**}`

- **Purpose**: "Polymorphic membership: applications, invitations, member roles
  for any subject" (`CONTEXT.md`); "adds reusable membership workflows to any
  Eloquent subject" (`docs/01-overview.md`). The usage doc's canonical example
  subject is literally a `Team` model (`docs/04-usage.md`).
- **Members are users**: `HasMembers::members()` builds
  `belongsToMany(config('auth.providers.users.model'), ...)` with pivot columns
  `role` + `joined_at` (`src/Traits/HasMembers.php:68-77`).
- **Roles are access control**: `MemberRole` = owner/admin/editor/viewer, each
  mapping to permission sets and a Spatie role name (`src/Enums/MemberRole.php`).
  `MembershipRoleSyncService` syncs pivot rows to Spatie team-roles via
  `aiarmada/authz` (`docs/01-overview.md`, "Role synchronization policy").
- **Applications grant roles**: `MembershipApplication` carries `subject_*`,
  `applicant_id` (a user), `granted_role`, justification, reviewer fields
  (`src/Models/MembershipApplication.php`).
- **Dependencies**: `aiarmada/authz`, `aiarmada/commerce-support`,
  `lorisleiva/laravel-actions` (`composer.json`).

## What `AffiliateProgramMembership` is

Source: `packages/affiliates/src/{Models/AffiliateProgramMembership.php,Models/Affiliate.php,Models/AffiliateProgramTier.php,Enums/MembershipStatus.php,Services/ProgramService.php}`

- **Members are affiliates, not users**: the pivot keys off `affiliate_id` +
  `program_id`. The `Affiliate` model has no `user_id` column and no `user()`
  relation — it is a standalone commercial record (code, status, commission,
  rank, parent).
- **Tiers are commercial, not access control**: `AffiliateProgramTier` carries
  `commission_rate_basis_points`, `min_conversions`, `min_revenue` thresholds,
  and `ProgramService::processTierUpgrades()` auto-promotes members up the
  ladder. There is no equivalent in `MemberRole`.
- **Richer lifecycle**: status is pending/approved/rejected/**suspended**
  (`Enums/MembershipStatus.php`); the pivot also holds `tier_id`,
  applied/approved/rejected/suspended/`expires_at` timestamps, `approved_by`,
  and per-member `custom_terms` (negotiated overrides). The generic pivot holds
  only `role` + `joined_at`.
- **Join rules live on the program**: `joinProgram()` enforces
  `canJoin($affiliate)`, `requires_approval`, and default-tier resolution
  (`Services/ProgramService.php:45-67`).
- **Contained blast radius**: `MembershipStatus::` is referenced only by the
  membership model and `ProgramService` — the network package never touches it.

## Point-by-point mismatch

| Need (affiliates) | `aiarmada/membership` offers | Gap |
|---|---|---|
| Member = `Affiliate` record (no user FK) | Member = auth user model, hardcoded | Identity mismatch; affiliates that aren't logins can't join |
| Commission tier + threshold auto-upgrade | Access role (owner/admin/editor/viewer) | No tier concept; roles sync to Spatie, meaningless for payouts |
| Suspended status, expiry, approved_by | Application pending/approved/rejected/cancelled | No suspension, no expiry on the grant |
| Per-member `custom_terms` | `meta` bag on the *application*, not the grant | Terms must live on the enrollment, not the request |
| `canJoin` / approval rules per program | Generic apply/approve actions | Would need re-implementing as hooks anyway |

## Cost of forcing it

- Map tiers onto roles (losing thresholds and the upgrade engine), or fork the
  generic pivot with commercial columns — at which point it is the current
  hand-rolled model with extra steps.
- Pull `aiarmada/authz` + Spatie role sync + `lorisleiva/laravel-actions` into
  the affiliates engine's dependency set for behavior affiliates never use.
- Rewrite the `Affiliate ↔ Program` relation, `ProgramService`, and the
  Filament admin around a user-shaped hole that affiliates don't fit.

## Is `aiarmada/membership` useful anywhere in the affiliate story?

- **Merchant staff teams** (who on the merchant side administers a program):
  plausible fit, but that is the `organizations` package's territory, which
  already integrates with membership (`packages/membership/CONTEXT.md`,
  "Related: ... organizations"). Nothing to build.
- **Network creators (rizq)**: no. Creators are solo users; there is no team,
  org, or role concept on the network side, and enrollment is an application
  row, not a membership.
- **Affiliate invitations**: the package's email-token invitation flow is the
  closest reusable piece, but affiliate recruitment (referral links, codes) is a
  different mechanic already owned by the engine.

## Recommendation

Keep `AffiliateProgramMembership` hand-rolled. It is small (~1 model + 1 enum +
`ProgramService` methods), dependency-free, and shaped exactly like the domain.
`aiarmada/membership` stays where it belongs: user teams and organizations.
