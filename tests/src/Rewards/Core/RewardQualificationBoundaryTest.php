<?php

declare(strict_types=1);

use AIArmada\Rewards\Actions\EnrollRewardMemberAction;
use AIArmada\Rewards\Actions\PreviewRewardQualificationAction;
use AIArmada\Rewards\Actions\TransitionRewardProgramStatusAction;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Enums\RewardPreviewDecision;
use AIArmada\Rewards\Enums\RewardPreviewReason;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Enums\RewardProgramStatus;
use AIArmada\Rewards\Enums\RewardVerificationLevel;
use AIArmada\Rewards\Models\RewardProgramVersion;
use AIArmada\Rewards\Support\RewardPreviewFixture;
use AIArmada\Rewards\Support\RewardRuleValidator;
use AIArmada\Rewards\Tests\Core\Support\RewardCoreTestFixtures;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

function c4Fixture(array $overrides = []): array
{
    return array_merge([
        'source_business_key' => 'share-outcome:attendance:c4-1',
        'outcome' => 'attendance',
        'category' => null,
        'occurred_at' => '2026-07-10T00:00:00Z',
        'verification' => 'accepted',
        'is_first_time' => true,
        'actor_id' => 'actor-1',
        'sharer_id' => 'sharer-1',
        'is_claimed_identity' => true,
        'is_attributed' => true,
    ], $overrides);
}

function c4Harness(string $prefix = 'core-c4'): array
{
    [$program] = RewardCoreTestFixtures::seedVersionedProgram($prefix);
    TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Ready);
    $program = TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Active);

    $memberId = (string) Str::uuid();
    $version = RewardProgramVersion::query()->where('program_id', $program->getKey())->orderByDesc('version')->first();
    EnrollRewardMemberAction::run($program, 'user', $memberId, $version, 'member-portal', CarbonImmutable::parse('2026-07-05T00:00:00Z'));

    return [$program->refresh(), $memberId];
}

it('rejects missing, empty, relative, and malformed fixture timestamps without guessing', function (): void {
    [$program] = c4Harness('core-c4-date');
    $decision = CarbonImmutable::parse('2026-07-11T00:00:00Z');

    foreach ([
        array_diff_key(c4Fixture(), ['occurred_at' => true]),
        c4Fixture(['occurred_at' => '']),
        c4Fixture(['occurred_at' => 'tomorrow']),
        c4Fixture(['occurred_at' => '+1 day']),
        c4Fixture(['occurred_at' => 'next Monday']),
        c4Fixture(['occurred_at' => 'not-a-date']),
        c4Fixture(['occurred_at' => '2026-07-10']),
    ] as $index => $bad) {
        expect(fn () => PreviewRewardQualificationAction::run($program, RewardComponent::Points, $bad, $decision))
            ->toThrow(InvalidArgumentException::class, 'explicit absolute timestamp', "case {$index}");
    }
});

it('rejects disabled or omitted protection flags at validation', function (): void {
    $validator = new RewardRuleValidator;

    foreach (['exclude_self_credit', 'require_enrollment', 'require_claimed_identity'] as $flag) {
        $missing = RewardCoreTestFixtures::rules();
        unset($missing['eligibility'][$flag]);
        expect($validator->errors($missing, RewardProgramMode::Points, 'own-family')[0])->toContain("Eligibility [{$flag}] must be true");

        $disabled = RewardCoreTestFixtures::rules();
        $disabled['eligibility'][$flag] = false;
        expect($validator->errors($disabled, RewardProgramMode::Points, 'own-family')[0])->toContain("Eligibility [{$flag}] must be true");
    }
});

it('rejects unclaimed, unattributed, bot, cancelled, and invalid sources', function (): void {
    [$program, $memberId] = c4Harness('core-c4-src');
    $member = ['member_type' => 'user', 'member_id' => $memberId];
    $decision = CarbonImmutable::parse('2026-07-11T00:00:00Z');

    $guest = PreviewRewardQualificationAction::run($program, RewardComponent::Points, c4Fixture(['is_claimed_identity' => false]), $decision, $member);
    expect($guest->reasons)->toBe([RewardPreviewReason::GuestUnclaimed]);

    $unattributed = PreviewRewardQualificationAction::run($program, RewardComponent::Points, c4Fixture(['is_attributed' => false]), $decision, $member);
    expect($unattributed->reasons)->toBe([RewardPreviewReason::LateAttributionRefused]);

    $bot = PreviewRewardQualificationAction::run($program, RewardComponent::Points, c4Fixture(['is_bot' => true]), $decision, $member);
    expect($bot->reasons)->toBe([RewardPreviewReason::SourceBotExcluded]);

    $cancelled = PreviewRewardQualificationAction::run($program, RewardComponent::Points, c4Fixture(['is_cancelled' => true]), $decision, $member);
    expect($cancelled->reasons)->toBe([RewardPreviewReason::SourceCancelled]);

    $invalid = PreviewRewardQualificationAction::run($program, RewardComponent::Points, c4Fixture(['is_valid_source' => false]), $decision, $member);
    expect($invalid->reasons)->toBe([RewardPreviewReason::SourceInvalid]);

    $self = PreviewRewardQualificationAction::run($program, RewardComponent::Points, c4Fixture(['actor_id' => 'same', 'sharer_id' => 'same']), $decision, $member);
    expect($self->reasons)->toBe([RewardPreviewReason::SelfCreditExcluded]);
});

it('lets actor-bound beneficiary roles earn unattributed evidence while sharer-bound roles still refuse', function (): void {
    [$program, $memberId] = c4Harness('core-c4-actor-attr');
    $member = ['member_type' => 'user', 'member_id' => $memberId];
    $decision = CarbonImmutable::parse('2026-07-11T00:00:00Z');

    $unattributed = ['is_attributed' => false];

    foreach (['recipient', 'sharer'] as $role) {
        $refused = PreviewRewardQualificationAction::run(
            $program,
            RewardComponent::Points,
            c4Fixture([...$unattributed, 'beneficiary_role' => $role]),
            $decision,
            $member,
        );

        expect($refused->decision)->toBe(RewardPreviewDecision::Ineligible)
            ->and($refused->reasons)->toBe([RewardPreviewReason::LateAttributionRefused]);
    }

    foreach (['participant', 'purchaser', 'staff'] as $role) {
        $eligible = PreviewRewardQualificationAction::run(
            $program,
            RewardComponent::Points,
            c4Fixture([...$unattributed, 'beneficiary_role' => $role]),
            $decision,
            $member,
        );

        expect($eligible->decision)->toBe(RewardPreviewDecision::Eligible)
            ->and($eligible->reasons)->toContain(RewardPreviewReason::Ok);
    }
});

it('rejects unknown beneficiary roles instead of silently defaulting', function (): void {
    [$program, $memberId] = c4Harness('core-c4-unknown-role');
    $member = ['member_type' => 'user', 'member_id' => $memberId];
    $decision = CarbonImmutable::parse('2026-07-11T00:00:00Z');

    expect(fn () => PreviewRewardQualificationAction::run(
        $program,
        RewardComponent::Points,
        c4Fixture(['beneficiary_role' => 'moderator']),
        $decision,
        $member,
    ))->toThrow(InvalidArgumentException::class, 'beneficiary_role');
});

it('applies per-kind verification without letting moderation vouch for attendance', function (): void {
    expect(RewardVerificationLevel::ModeratedApproved->satisfiesFor(RewardVerificationLevel::StaffVerified, 'contribution'))->toBeTrue();
    expect(RewardVerificationLevel::ModeratedApproved->satisfiesFor(RewardVerificationLevel::StaffVerified, 'attendance'))->toBeFalse();
    expect(RewardVerificationLevel::ModeratedApproved->satisfiesFor(RewardVerificationLevel::Accepted, 'registration'))->toBeFalse();
    expect(RewardVerificationLevel::StaffVerified->satisfiesFor(RewardVerificationLevel::StaffVerified, 'attendance'))->toBeTrue();

    [$program, $memberId] = c4Harness('core-c4-verify');
    $member = ['member_type' => 'user', 'member_id' => $memberId];
    $decision = CarbonImmutable::parse('2026-07-11T00:00:00Z');

    $mismatch = PreviewRewardQualificationAction::run(
        $program,
        RewardComponent::Points,
        c4Fixture(['outcome' => 'attendance', 'verification' => 'moderated_approved']),
        $decision,
        $member,
    );

    expect($mismatch->reasons)->toBe([RewardPreviewReason::VerificationInsufficient]);

    $submission = PreviewRewardQualificationAction::run(
        $program,
        RewardComponent::Points,
        c4Fixture(['source_business_key' => 'share-outcome:contribution:c4-2', 'outcome' => 'contribution', 'verification' => 'moderated_approved']),
        $decision,
        $member,
    );

    expect($submission->decision)->toBe(RewardPreviewDecision::Eligible);
});

it('validates directly constructed fixtures with the same invariants', function (): void {
    expect(fn () => new RewardPreviewFixture(
        sourceBusinessKey: '',
        outcome: 'attendance',
        category: null,
        occurredAt: CarbonImmutable::parse('2026-07-10T00:00:00Z'),
        verification: RewardVerificationLevel::Accepted,
        isFirstTime: true,
        actorId: null,
        sharerId: null,
        isClaimedIdentity: true,
        isAttributed: true,
    ))->toThrow(InvalidArgumentException::class, 'source_business_key');

    expect(fn () => new RewardPreviewFixture(
        sourceBusinessKey: 'share-outcome:attendance:c4-3',
        outcome: 'attendance',
        category: 'BAD CATEGORY!',
        occurredAt: CarbonImmutable::parse('2026-07-10T00:00:00Z'),
        verification: RewardVerificationLevel::Accepted,
        isFirstTime: true,
        actorId: null,
        sharerId: null,
        isClaimedIdentity: true,
        isAttributed: true,
    ))->toThrow(InvalidArgumentException::class, 'category');
});
