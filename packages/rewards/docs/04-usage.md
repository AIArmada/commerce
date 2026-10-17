---
title: Rewards usage
---

# Rewards usage

All flows run through invokable actions (`lorisleiva/laravel-actions`). Every example below is copy-paste ready.

## Create and activate a program

```php
use AIArmada\Rewards\Actions\CreateRewardProgramAction;
use AIArmada\Rewards\Actions\PublishRewardProgramVersionAction;
use AIArmada\Rewards\Actions\TransitionRewardProgramStatusAction;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Enums\RewardProgramStatus;
use AIArmada\Rewards\Support\RewardPresetBuilders;
use Carbon\CarbonImmutable;

$program = CreateRewardProgramAction::run(
    familyKey: 'community-champions',
    title: 'Community Champions',
    mode: RewardProgramMode::Points,
    ownerType: $organization->getMorphClass(), // or null for a global program
    ownerId: (string) $organization->getKey(), // or null for a global program
    purpose: 'Recognise sustained community contribution',
);

$preset = RewardPresetBuilders::for(RewardProgramMode::Points);
$effective = CarbonImmutable::parse('2026-06-01T00:00:00Z');

PublishRewardProgramVersionAction::run($program, $effective, $preset['rules'], $preset['terms']);

TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Ready);
TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Active);
```

## Enroll a member and evaluate

```php
use AIArmada\Rewards\Actions\EnrollRewardMemberAction;
use AIArmada\Rewards\Actions\EvaluateRewardEntitlementAction;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;

EnrollRewardMemberAction::run($program, 'user', $memberId, $version, 'member-portal', $enrolledAt);

$result = EvaluateRewardEntitlementAction::run(
    $program, RewardComponent::Points, 'user', $memberId,
    RewardBeneficiaryRole::Recipient, $sourceBusinessKey, $occurredAt,
);

if ($result->duplicate) {
    // idempotent replay: the original award is returned, nothing minted twice
}
```

Bind a `RewardSourceFactReader` so evaluation can read current source facts; the null reader refuses every key.

## Beneficiary roles and attribution

The beneficiary role names *which identity on the fact earns* and decides whether attribution is required. The single predicate is `RewardBeneficiaryRole::requiresAttribution()`:

| Role | Binding | Unattributed evidence |
| --- | --- | --- |
| `Recipient` | fact's sharer id | refused (`late_attribution_refused`) |
| `Sharer` | fact's sharer id | refused (`late_attribution_refused`) |
| `Participant` | fact's actor id | eligible — the actor's own act |
| `Purchaser` | fact's actor id | eligible — the actor's own act |
| `Staff` | fact's actor id **and** actor role `staff` | eligible — the actor's own act |

```php
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;

// Sharer-bound (default): the fact must be attributed, or the
// evaluation freezes a permanent `late_attribution_refused` rejection.
EvaluateRewardEntitlementAction::run(
    $program, RewardComponent::Points, 'user', $memberId,
    RewardBeneficiaryRole::Recipient, $sourceBusinessKey, $occurredAt,
);

// Actor-bound: the organizer's unattributed attendance still earns.
EvaluateRewardEntitlementAction::run(
    $program, RewardComponent::Points, 'user', $memberId,
    RewardBeneficiaryRole::Participant, $sourceBusinessKey, $occurredAt,
);
```

The same split governs every surface, so an actor-bound fact earns consistently everywhere:

- **Queued candidates** — `RewardEvaluationCandidates` drops only the sharer side of an unattributed fact; the actor side still emits (a `staff` actor role keeps its `Staff` candidate, never collapsing to `Participant`). Invalid and cancelled facts stay silent.
- **Milestones** — each contribution binds its stored `contributor_role` to the recipient: sharer-bound roles need `sharerId`, actor-bound roles need `actorId`, and `Staff` additionally requires the fact's actor role. The minted milestone award carries the achieving contribution's role, and transition rechecks gate attribution and binding per stored contributor role.
- **Transitions** — the frozen-qualification recheck re-runs the pure engine under the award's own `beneficiary_role`, so a held actor-bound award earned on an unattributed fact advances while a sharer-bound one keeps refusing.
- **Preview sandbox** — fixtures carry a `beneficiary_role` key (values from `RewardBeneficiaryRole::values()`, default `recipient`; unknown values throw). The fail-closed default means existing callers and stored fixtures keep the old sharer-bound behavior.

## Outcome-scoped component amounts and milestones

Published versions may define multiple non-overlapping variants under a
component. Each variant scopes its grant to eligible outcomes, beneficiary
roles, a minimum verification level, and the first-only rule:

```php
'components' => [[
    'type' => 'points',
    'variants' => [
        [
            'outcomes' => ['event_registration'],
            'roles' => ['participant', 'sharer'],
            'min_verification' => 'accepted',
            'first_only' => false,
            'points' => 5,
            'unit' => 'pts',
        ],
        [
            'outcomes' => ['event_checkin'],
            'roles' => ['participant', 'sharer'],
            'min_verification' => 'staff_verified',
            'first_only' => false,
            'points' => 15,
            'unit' => 'pts',
        ],
    ],
]],
```

Variants for one component cannot overlap on the same outcome and role. Their
award snapshots carry the selected variant, so later edits affect only a new
published version. Existing versions that store one flat component entry
remain readable and keep their original award behavior.

Versions may also contain `milestones`, each with a stable `key`, member-facing
`label`, eligible `outcomes` and `roles`, `min_verification`, `first_only`, a
`target_count`, a rolling `window_days`, and a `reward` grant. Contributions
and completion rechecks use that frozen definition; badge, points, and
recognition grants are supported. Milestone labels and grants are private
program data and do not imply a public ranking.

## Cash payouts (manual rail)

```php
use AIArmada\Rewards\Actions\ApproveRewardPayoutAction;
use AIArmada\Rewards\Actions\ConfirmRewardPayoutAction;
use AIArmada\Rewards\Actions\RecordRewardFundingAction;
use AIArmada\Rewards\Actions\RecordRewardFundingSourceAction;
use AIArmada\Rewards\Actions\RegisterRewardPayeeAction;
use AIArmada\Rewards\Actions\RequestRewardPayoutAction;
use AIArmada\Rewards\Actions\SubmitRewardPayoutAction;
use AIArmada\Rewards\Actions\VerifyRewardPayeeAction;

RecordRewardFundingSourceAction::run('sponsor-2026', 'MYR', 100000, 'user', $staffId, 'staff');
RecordRewardFundingAction::run($program, 'MYR', 100000, 'sponsor-2026', 'user', $staffId, 'staff');

$payee = RegisterRewardPayeeAction::run('user', $memberId, 'manual', 'acct-1', 'Manual handoff', 'user', $memberId, 'member');
VerifyRewardPayeeAction::run('user', $memberId, 'manual', 'acct-1', 'Manual handoff', 'user', $staffId, 'staff');

$request = RequestRewardPayoutAction::run($award, $payee, 'manual', 'program', 'user', $staffId, 'staff');
ApproveRewardPayoutAction::run($request, 'user', $approverId, 'staff', 'looks-good');
SubmitRewardPayoutAction::run($request, 'user', $staffId, 'staff'); // manual rail: submitted, awaits confirmation
ConfirmRewardPayoutAction::run($request, ['bank_reference' => 'bank-ref-1', 'transferred_at' => '2026-06-17T00:00:00Z'], 'user', $confirmerId, 'staff');
```

## Corrections sweep

```php
use AIArmada\Rewards\Actions\ApplyRewardSourceCorrectionsAction;

$report = ApplyRewardSourceCorrectionsAction::run('user', $staffId, 'staff', $decided, 200, [
    ['business_key' => $key, 'reason' => 'source-correction', 'evidence' => ['business_key' => $key]],
]);
// $report: reversed, rejected, recoveries, contributions, skipped, errors
```

Or via Artisan with explicit keys (or the bound `RewardCorrectionKeySource`):

```bash
php artisan rewards:apply-source-corrections --actor=<uuid> --actor-role=staff --keys=key-1,key-2
php artisan rewards:expire-point-lots --actor=<uuid> --actor-role=staff
```

Integrations may also drive explicit keys directly: the referrals points
rail, for example, calls the sweep with `referrals:qualified:{inviteId}:{side}`
as an explicit tainted key when a referral reward reverses, so the referral
settles only once its live side award is rejected or reversed. Explicit caller
keys are trusted by design — the caller owns the business meaning of the key.

System runs (`EvaluateRewardEntitlementJob`, `RecheckRewardAwardsJob`, the
corrections sweep, and the sweep command) need no ambient owner: every
unit of work fans out under the owning program's own owner context via
`RewardProgramGuard::withProgramOwner()`. Global (platform) programs run
under an explicit-global context. A program whose owner row was deleted
fails per award — the queued job retries/dead-letters, and the sweep
records a per-award entry in `errors` and continues with the rest.

That fan-out is the owner-less case only. When an owner is already
resolved — including an explicit-global context — the run stays scoped to
it, so a synchronous in-tenant dispatch such as the referrals points rail
never reaches another tenant's programs.

Related: [configuration](03-configuration.md), [troubleshooting](99-troubleshooting.md).
