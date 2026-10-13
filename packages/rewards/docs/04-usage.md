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

Related: [configuration](03-configuration.md), [troubleshooting](99-troubleshooting.md).
