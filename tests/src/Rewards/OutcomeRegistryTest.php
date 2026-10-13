<?php

declare(strict_types=1);

use AIArmada\Rewards\Actions\EvaluateRewardEntitlementAction;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Support\RewardLedgerEntries;
use AIArmada\Rewards\Support\RewardPresetBuilders;
use AIArmada\Rewards\Support\RewardRuleValidator;
use AIArmada\Rewards\Tests\Ledgers\Support\FakeRewardSourceFactReader;
use AIArmada\Rewards\Tests\Ledgers\Support\RewardLedgerFixtures;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

test('unknown outcomes are rejected from rules', function (): void {
    $rules = RewardPresetBuilders::for(RewardProgramMode::Points)['rules'];
    $rules['eligibility']['outcomes'] = ['contribution', 'nope-not-registered'];

    $errors = (new RewardRuleValidator)->errors($rules, RewardProgramMode::Points, 'registry');

    expect($errors)->not->toBe([])
        ->and(implode("\n", $errors))->toContain('allowlisted');
});

test('unknown recipient types are refused before any award freezes', function (): void {
    [$program] = RewardLedgerFixtures::seedActiveEnrolled('registry-recip');

    $reader = new FakeRewardSourceFactReader;
    $memberId = (string) Str::uuid();
    $reader->seed(RewardLedgerFixtures::fact('src-registry-recip-1', $memberId));

    expect(fn () => (new EvaluateRewardEntitlementAction($reader))->handle(
        $program,
        RewardComponent::Points,
        'ghost',
        $memberId,
        RewardBeneficiaryRole::Recipient,
        'src-registry-recip-1',
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    ))->toThrow(InvalidArgumentException::class, 'Recipient type must be one of');
});

test('empty registries fail closed', function (): void {
    config(['rewards.outcomes' => [], 'rewards.recipient_types' => []]);

    expect(RewardRuleValidator::outcomes())->toBe([])
        ->and(RewardLedgerEntries::recipientTypes())->toBe([]);

    $rules = RewardPresetBuilders::for(RewardProgramMode::Points)['rules'];

    expect($rules['eligibility']['outcomes'])->toBe([])
        ->and((new RewardRuleValidator)->errors($rules, RewardProgramMode::Points, 'registry'))->not->toBe([]);
});

test('beneficiary roles enumerate their values', function (): void {
    expect(RewardBeneficiaryRole::values())->toBe(['recipient', 'participant', 'purchaser', 'staff', 'sharer']);
});

test('host outcome vocabulary is absent from core defaults', function (): void {
    $packageConfig = require __DIR__ . '/../../../packages/rewards/config/rewards.php';

    expect($packageConfig['outcomes'])->toBe([])
        ->and($packageConfig['recipient_types'])->toBe([])
        ->and(json_encode($packageConfig))->not->toContain('event_checkin')
        ->and(json_encode($packageConfig))->not->toContain('signup');
});
