<?php

declare(strict_types=1);

namespace Tests\src\Referrals;

use AIArmada\Affiliates\Data\AffiliateAwardDecision;
use AIArmada\Affiliates\Data\AffiliateAwardProposal;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Referrals\Actions\AcceptReferralInvite;
use AIArmada\Referrals\Actions\CreateReferralInvite;
use AIArmada\Referrals\Actions\CreateReferralProgram;
use AIArmada\Referrals\Actions\FulfillReferralReward;
use AIArmada\Referrals\Actions\QualifyReferral;
use AIArmada\Referrals\Actions\ReverseReferralReward;
use AIArmada\Referrals\Actions\RevokeReferralInvite;
use AIArmada\Referrals\Actions\UpdateReferralProgram;
use AIArmada\Referrals\Contracts\ReferralAffiliateResolver;
use AIArmada\Referrals\Contracts\ReferralRewardFulfillment;
use AIArmada\Referrals\Data\ReferralFulfillmentResult;
use AIArmada\Referrals\Data\ReferralInviteCreationResult;
use AIArmada\Referrals\Enums\ReferralInviteStatus;
use AIArmada\Referrals\Enums\ReferralProgramStatus;
use AIArmada\Referrals\Enums\ReferralRewardStatus;
use AIArmada\Referrals\Exceptions\ReferralAlreadyClaimedException;
use AIArmada\Referrals\Exceptions\ReferralFulfillmentException;
use AIArmada\Referrals\Exceptions\ReferralInviteNotFoundException;
use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\Models\ReferralReward;
use AIArmada\Referrals\Support\ReferralAffiliateAwardPolicy;
use AIArmada\Referrals\Support\ReferralContactNormalizer;
use AIArmada\Referrals\Support\ReferralProgramAttributeNormalizer;
use AIArmada\Referrals\Support\ReferralProgramGuard;
use AIArmada\Referrals\Support\ReferralRewardSpecValidator;
use AIArmada\Rewards\Actions\ApplyRewardSourceCorrectionsAction;
use AIArmada\Rewards\Actions\EnrollRewardMemberAction;
use AIArmada\Rewards\Actions\MarkRewardReservationSubmittedAction;
use AIArmada\Rewards\Actions\ReserveRewardPointsAction;
use AIArmada\Rewards\Actions\TransitionRewardAwardAction;
use AIArmada\Rewards\Actions\TransitionRewardProgramStatusAction;
use AIArmada\Rewards\Contracts\RewardSourceFactReader;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardMilestoneStatus;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Enums\RewardProgramStatus;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardMilestoneContribution;
use AIArmada\Rewards\Models\RewardMilestoneCycle;
use AIArmada\Rewards\Models\RewardPointReservationSubmission;
use AIArmada\Rewards\Support\RewardPointLedger;
use AIArmada\Rewards\Tests\Ledgers\Support\FakeRewardSourceFactReader;
use AIArmada\Rewards\Tests\Ledgers\Support\RewardLedgerFixtures;
use AIArmada\Vouchers\Actions\RecordVoucherUsage;
use AIArmada\Vouchers\Actions\ValidateVoucherCode;
use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Models\Voucher;
use Akaunting\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Mockery;
use RuntimeException;

uses(ReferralsTestCase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('completes a two-sided referral lifecycle and rewards the invite', function (): void {
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'first.member@example.com']);
    $program = $this->newProgram($owner);
    $created = $this->newInvite($owner, $program, $referrer, $invitee->email, $referrer->email);
    $accepted = $this->acceptInvite($owner, (string) $created->token, $invitee, $invitee->email);
    $qualified = OwnerContext::withOwner($owner, fn () => QualifyReferral::run($accepted, 'registration:' . $invitee->getKey()));

    expect($qualified->rewards)->toHaveCount(2);

    foreach ($qualified->rewards as $index => $reward) {
        $reward->metadata = ['external_reference' => 'receipt-' . $index];
        $reward->save();
        OwnerContext::withOwner($owner, fn () => FulfillReferralReward::run($reward));
    }

    expect($accepted->fresh()->status)->toBe(ReferralInviteStatus::Rewarded)
        ->and($accepted->fresh()->rewarded_at)->not->toBeNull()
        ->and($accepted->rewards()->where('status', ReferralRewardStatus::Fulfilled->value)->count())->toBe(2);
});

it('returns an existing live invite to the same referrer and rejects a second referrer', function (): void {
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $secondReferrer = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'claim@example.com']);
    $program = $this->newProgram($owner);
    $first = $this->newInvite($owner, $program, $referrer, 'claim@example.com');
    $reissued = $this->newInvite($owner, $program, $referrer, 'claim@example.com');

    expect($reissued->invite->is($first->invite))->toBeTrue()
        ->and($reissued->token)->toBeString()
        ->and($reissued->invite->token_hash)->toBe(hash('sha256', $reissued->token))
        ->and($reissued->invite->token_hash)->not->toBe(hash('sha256', $first->token));

    expect(fn () => $this->acceptInvite($owner, (string) $first->token, $invitee, $invitee->email))
        ->toThrow(ReferralInviteNotFoundException::class);

    $accepted = $this->acceptInvite($owner, (string) $reissued->token, $invitee, $invitee->email);
    $acceptedTokenHash = $accepted->token_hash;
    $acceptedReissue = $this->newInvite($owner, $program, $referrer, 'claim@example.com');

    expect($acceptedReissue->invite->status)->toBe(ReferralInviteStatus::Accepted)
        ->and($acceptedReissue->token)->toBeNull()
        ->and($acceptedReissue->invite->token_hash)->toBe($acceptedTokenHash);

    expect(fn () => $this->newInvite($owner, $program, $secondReferrer, 'claim@example.com'))
        ->toThrow(ReferralAlreadyClaimedException::class);
});

it('expires a stale pending invite before creating a replacement', function (): void {
    $start = CarbonImmutable::parse('2026-01-01 12:00:00 UTC');
    CarbonImmutable::setTestNow($start);
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $program = $this->newProgram($owner, ['invite_expiry_days' => 1]);
    $expired = $this->newInvite($owner, $program, $referrer, 'expired-reissue@example.com');
    CarbonImmutable::setTestNow($start->addDays(2));

    $replacement = $this->newInvite($owner, $program, $referrer, 'expired-reissue@example.com');

    expect($replacement->invite->is($expired->invite))->toBeFalse()
        ->and($replacement->token)->toBeString()
        ->and($expired->invite->fresh()->status)->toBe(ReferralInviteStatus::Expired);
});

it('rejects a self-invite when the host supplies the referrer contact', function (): void {
    $owner = $this->newOwner();
    $referrer = User::factory()->create(['email' => 'owner@example.com']);
    $program = $this->newProgram($owner);

    expect(fn () => $this->newInvite($owner, $program, $referrer, ' OWNER@example.com ', $referrer->email))
        ->toThrow(LogicException::class, 'cannot invite their own contact');
});

it('rejects acceptance with a different contact and leaves the invite pending', function (): void {
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $invitee = User::factory()->create();
    $program = $this->newProgram($owner);
    $created = $this->newInvite($owner, $program, $referrer, 'addressed@example.com');

    expect(fn () => $this->acceptInvite($owner, (string) $created->token, $invitee, 'wrong@example.com'))
        ->toThrow(ReferralInviteNotFoundException::class);

    expect($created->invite->fresh()->status)->toBe(ReferralInviteStatus::Pending);
});

it('rejects acceptance by the referrer morph and leaves the invite pending', function (): void {
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $program = $this->newProgram($owner);
    $created = $this->newInvite($owner, $program, $referrer, 'other.contact@example.com');

    expect(fn () => $this->acceptInvite($owner, (string) $created->token, $referrer, 'other.contact@example.com'))
        ->toThrow(ReferralInviteNotFoundException::class);

    expect($created->invite->fresh()->status)->toBe(ReferralInviteStatus::Pending);
});

it('rejects acceptance after the invite expiry instant', function (): void {
    $start = CarbonImmutable::parse('2026-01-01 12:00:00 UTC');
    CarbonImmutable::setTestNow($start);
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'expired@example.com']);
    $program = $this->newProgram($owner, ['invite_expiry_days' => 1]);
    $created = $this->newInvite($owner, $program, $referrer, $invitee->email);
    CarbonImmutable::setTestNow($start->addDays(2));

    expect(fn () => $this->acceptInvite($owner, (string) $created->token, $invitee, $invitee->email))
        ->toThrow(ReferralInviteNotFoundException::class);
});

it('rejects qualification after an accepted invite expires', function (): void {
    $start = CarbonImmutable::parse('2026-02-01 12:00:00 UTC');
    CarbonImmutable::setTestNow($start);
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'late-registration@example.com']);
    $program = $this->newProgram($owner, ['invite_expiry_days' => 1]);
    $created = $this->newInvite($owner, $program, $referrer, $invitee->email);
    $accepted = $this->acceptInvite($owner, (string) $created->token, $invitee, $invitee->email);
    CarbonImmutable::setTestNow($start->addDays(2));

    expect(fn () => OwnerContext::withOwner($owner, fn () => QualifyReferral::run($accepted, 'late-registration')))
        ->toThrow(LogicException::class, 'expired referral invite');
});

it('rejects qualification before the invite acceptance instant', function (): void {
    $start = CarbonImmutable::parse('2026-02-10 12:00:00 UTC');
    CarbonImmutable::setTestNow($start);
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'backdated@example.com']);
    $program = $this->newProgram($owner);
    $created = $this->newInvite($owner, $program, $referrer, $invitee->email);
    CarbonImmutable::setTestNow($start->addHour());
    $accepted = $this->acceptInvite($owner, (string) $created->token, $invitee, $invitee->email);

    expect(fn () => OwnerContext::withOwner(
        $owner,
        fn () => QualifyReferral::run($accepted, 'registration:backdated', $start->addMinutes(30)),
    ))->toThrow(LogicException::class, 'before the invite was accepted');

    expect($accepted->fresh()->status)->toBe(ReferralInviteStatus::Accepted)
        ->and($accepted->fresh()->qualified_at)->toBeNull();
});

it('keeps revoked invites terminal', function (): void {
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'revoked@example.com']);
    $program = $this->newProgram($owner);
    $created = $this->newInvite($owner, $program, $referrer, $invitee->email);
    $revoked = OwnerContext::withOwner($owner, fn (): ReferralInvite => RevokeReferralInvite::run($created->invite, 'requested'));

    expect($revoked->status)->toBe(ReferralInviteStatus::Revoked)
        ->and($revoked->revoked_at)->not->toBeNull()
        ->and(fn () => $this->acceptInvite($owner, (string) $created->token, $invitee, $invitee->email))
        ->toThrow(ReferralInviteNotFoundException::class);
});

it('replays qualification for the same or another key without creating more rewards', function (): void {
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'replay@example.com']);
    $program = $this->newProgram($owner);
    $created = $this->newInvite($owner, $program, $referrer, $invitee->email);
    $accepted = $this->acceptInvite($owner, (string) $created->token, $invitee, $invitee->email);

    $first = OwnerContext::withOwner($owner, fn () => QualifyReferral::run($accepted, 'registration:replay'));
    $sameKey = OwnerContext::withOwner($owner, fn () => QualifyReferral::run($accepted, 'registration:replay'));
    $differentKey = OwnerContext::withOwner($owner, fn () => QualifyReferral::run($accepted, 'registration:other'));

    expect($first->rewards)->toHaveCount(2)
        ->and($sameKey->rewards)->toHaveCount(2)
        ->and($differentKey->rewards)->toHaveCount(2)
        ->and(ReferralReward::query()->where('invite_id', $accepted->getKey())->count())->toBe(2)
        ->and($accepted->fresh()->qualification_business_key)->toBe('registration:replay');
});

it('enforces the per-referrer monthly qualification cap', function (): void {
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $firstInvitee = User::factory()->create(['email' => 'cap-one@example.com']);
    $secondInvitee = User::factory()->create(['email' => 'cap-two@example.com']);
    $program = $this->newProgram($owner, ['max_qualified_per_referrer_per_month' => 1]);
    $first = $this->newInvite($owner, $program, $referrer, $firstInvitee->email);
    $second = $this->newInvite($owner, $program, $referrer, $secondInvitee->email);
    $firstAccepted = $this->acceptInvite($owner, (string) $first->token, $firstInvitee, $firstInvitee->email);
    $secondAccepted = $this->acceptInvite($owner, (string) $second->token, $secondInvitee, $secondInvitee->email);

    OwnerContext::withOwner($owner, fn () => QualifyReferral::run($firstAccepted, 'registration:cap-one'));

    expect(fn () => OwnerContext::withOwner($owner, fn () => QualifyReferral::run($secondAccepted, 'registration:cap-two')))
        ->toThrow(LogicException::class, 'monthly qualification cap');
});

it('enforces the active invite cap for a referrer', function (): void {
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $program = $this->newProgram($owner, ['max_invites_per_referrer' => 1]);
    $this->newInvite($owner, $program, $referrer, 'cap-first@example.com');

    expect(fn () => $this->newInvite($owner, $program, $referrer, 'cap-second@example.com'))
        ->toThrow(LogicException::class, 'invite cap');
});

it('prevents another owner from resolving, accepting, qualifying, or fulfilling another owners records', function (): void {
    $ownerA = $this->newOwner();
    $ownerB = $this->newOwner();
    $referrer = User::factory()->create();
    $pendingInvitee = User::factory()->create(['email' => 'tenant-pending@example.com']);
    $acceptedInvitee = User::factory()->create(['email' => 'tenant-accepted@example.com']);
    $rewardInvitee = User::factory()->create(['email' => 'tenant-reward@example.com']);
    $program = $this->newProgram($ownerA);
    $pending = $this->newInvite($ownerA, $program, $referrer, $pendingInvitee->email);
    $acceptedCreated = $this->newInvite($ownerA, $program, $referrer, $acceptedInvitee->email);
    $accepted = $this->acceptInvite($ownerA, (string) $acceptedCreated->token, $acceptedInvitee, $acceptedInvitee->email);
    $rewardCreated = $this->newInvite($ownerA, $program, $referrer, $rewardInvitee->email);
    $rewardAccepted = $this->acceptInvite($ownerA, (string) $rewardCreated->token, $rewardInvitee, $rewardInvitee->email);
    $qualified = OwnerContext::withOwner($ownerA, fn () => QualifyReferral::run($rewardAccepted, 'registration:tenant-reward'));
    $reward = $qualified->rewards[0];

    expect(fn () => $this->acceptInvite($ownerB, (string) $pending->token, $pendingInvitee, $pendingInvitee->email))
        ->toThrow(ReferralInviteNotFoundException::class);
    expect(fn () => OwnerContext::withOwner($ownerB, fn () => QualifyReferral::run($accepted, 'registration:foreign')))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => OwnerContext::withOwner($ownerB, fn () => FulfillReferralReward::run($reward)))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => OwnerContext::withOwner($ownerB, fn () => app(ReferralProgramGuard::class)->invite($accepted)))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => OwnerContext::withOwner($ownerB, fn () => app(ReferralProgramGuard::class)->reward($reward)))
        ->toThrow(ModelNotFoundException::class);
});

it('requires a manual reference, fulfils only once, and returns a rewarded invite to qualified on reversal', function (): void {
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'manual@example.com']);
    $program = $this->newProgram($owner, ['referee_reward' => null]);
    $created = $this->newInvite($owner, $program, $referrer, $invitee->email);
    $accepted = $this->acceptInvite($owner, (string) $created->token, $invitee, $invitee->email);
    $qualified = OwnerContext::withOwner($owner, fn () => QualifyReferral::run($accepted, 'registration:manual'));
    $reward = $qualified->rewards[0];

    $failed = OwnerContext::withOwner($owner, fn () => FulfillReferralReward::run($reward));

    expect($failed->status)->toBe(ReferralRewardStatus::Failed)
        ->and($failed->metadata['fulfillment_failure'])->toContain('external_reference');

    $failed->metadata = ['external_reference' => 'manual-receipt'];
    $failed->save();
    $firstFulfilment = OwnerContext::withOwner($owner, fn () => FulfillReferralReward::run($failed));
    $fulfilledAt = $firstFulfilment->fulfilled_at;
    $replay = OwnerContext::withOwner($owner, fn () => FulfillReferralReward::run($firstFulfilment));

    expect($firstFulfilment->status)->toBe(ReferralRewardStatus::Fulfilled)
        ->and($replay->status)->toBe(ReferralRewardStatus::Fulfilled)
        ->and($replay->external_reference)->toBe('manual-receipt')
        ->and($replay->fulfilled_at->equalTo($fulfilledAt))->toBeTrue()
        ->and($accepted->fresh()->status)->toBe(ReferralInviteStatus::Rewarded)
        ->and($accepted->fresh()->rewarded_at)->not->toBeNull();

    $unclearedDemotion = $accepted->fresh();
    $unclearedDemotion->status = ReferralInviteStatus::Qualified;

    expect(fn () => $unclearedDemotion->save())
        ->toThrow(LogicException::class, 'must clear rewarded_at');

    $reversed = OwnerContext::withOwner($owner, fn (): ReferralReward => ReverseReferralReward::run($replay, 'fraud review'));

    expect($reversed->status)->toBe(ReferralRewardStatus::Reversed)
        ->and($reversed->reversed_at)->not->toBeNull()
        ->and($accepted->fresh()->status)->toBe(ReferralInviteStatus::Qualified)
        ->and($accepted->fresh()->rewarded_at)->toBeNull();
});

it('fulfils each referral side against its own role, recipient, and family', function (): void {
    $start = CarbonImmutable::parse('2026-06-15T12:00:00Z');
    CarbonImmutable::setTestNow($start);
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'points-referral@example.com']);
    $staff = User::factory()->create();
    $recipientType = $referrer->getMorphClass();
    config()->set('rewards.outcomes', ['referral_qualified']);
    config()->set('rewards.recipient_types', [$recipientType]);

    // Distinct point values on the two sides stay legal because each side
    // binds its own rewards family: one program's points component carries
    // one value, so a same-family (or family-less) program cannot promise
    // 10 to the referrer and 20 to the referee.
    $referralProgram = $this->newProgram($owner, [
        'referrer_reward' => ['driver' => 'reward_points', 'points' => 10, 'family_key' => 'referral-share'],
        'referee_reward' => ['driver' => 'reward_points', 'points' => 20, 'family_key' => 'referee-join'],
    ]);
    $created = $this->newInvite($owner, $referralProgram, $referrer, $invitee->email);
    $accepted = $this->acceptInvite($owner, (string) $created->token, $invitee, $invitee->email);
    $qualified = OwnerContext::withOwner(
        $owner,
        fn () => QualifyReferral::run($accepted, 'registration:points-referral'),
    );
    $referrerReward = $qualified->rewards[0];
    $refereeReward = $qualified->rewards[1];
    $businessKey = 'referrals:qualified:' . $referrerReward->invite_id . ':referrer';
    $refereeBusinessKey = 'referrals:qualified:' . $refereeReward->invite_id . ':referee';

    $reader = new FakeRewardSourceFactReader;
    // The referrer is the attributed sharer of the invited registration.
    $reader->seed(RewardLedgerFixtures::fact(
        $businessKey,
        (string) $referrer->getKey(),
        '2026-06-12T00:00:00Z',
        'referral_qualified',
        ['actorId' => (string) $invitee->getKey()],
    ));
    // The referee is the acting registrant on the same attribution.
    $reader->seed(RewardLedgerFixtures::fact(
        $refereeBusinessKey,
        (string) $referrer->getKey(),
        '2026-06-12T00:00:00Z',
        'referral_qualified',
        ['actorId' => (string) $invitee->getKey()],
    ));
    app()->instance(RewardSourceFactReader::class, $reader);

    [$rewardsProgram, $version] = OwnerContext::withOwner(
        $owner,
        fn (): array => RewardLedgerFixtures::seedVersionedProgram(
            'referral-points',
            RewardProgramMode::Points,
            ['eligibility' => ['outcomes' => ['referral_qualified']]],
            '2026-06-01T00:00:00Z',
            'referral-share',
        ),
    );
    OwnerContext::withOwner($owner, function () use ($rewardsProgram): void {
        TransitionRewardProgramStatusAction::run($rewardsProgram, RewardProgramStatus::Ready);
        TransitionRewardProgramStatusAction::run($rewardsProgram, RewardProgramStatus::Active);
    });

    $failed = OwnerContext::withOwner($owner, fn () => FulfillReferralReward::run($referrerReward));

    expect($failed->status)->toBe(ReferralRewardStatus::Failed)
        ->and($failed->metadata['fulfillment_failure'])->toContain('without creating')
        ->and(RewardAward::query()->count())->toBe(0);

    OwnerContext::withOwner($owner, function () use ($rewardsProgram, $version, $referrer, $invitee, $recipientType, $start): void {
        EnrollRewardMemberAction::run(
            $rewardsProgram,
            $recipientType,
            (string) $referrer->getKey(),
            $version,
            'member-portal',
            $start,
        );
        EnrollRewardMemberAction::run(
            $rewardsProgram,
            $recipientType,
            (string) $invitee->getKey(),
            $version,
            'member-portal',
            $start,
        );
    });

    $fulfilled = OwnerContext::withOwner($owner, fn () => FulfillReferralReward::run($failed));
    $referrerAward = RewardAward::query()
        ->where('source_business_key', $businessKey)
        ->where('recipient_type', $recipientType)
        ->where('recipient_id', $referrer->getKey())
        ->where('component', 'points')
        ->firstOrFail();

    // Exactly one candidate was evaluated: the frozen sharer role bound to
    // the referrer, inside the family-bound program. The whole-fact dispatch
    // evaluated every (program, component, side) tuple and left orphans.
    expect($fulfilled->status)->toBe(ReferralRewardStatus::Fulfilled)
        ->and(RewardAward::query()->count())->toBe(1)
        ->and($referrerAward->beneficiary_role)->toBe(RewardBeneficiaryRole::Sharer)
        ->and($referrerAward->family_key)->toBe('referral-share')
        ->and($referrerAward->amount_snapshot['points'])->toBe(10);

    // The referee side binds its own family and the acting registrant. The
    // family-bound program pays 15 points, one short of the frozen 20.
    [$refereeProgram, $refereeVersion] = OwnerContext::withOwner(
        $owner,
        fn (): array => RewardLedgerFixtures::seedVersionedProgram(
            'referee-points',
            RewardProgramMode::Points,
            [
                'eligibility' => ['outcomes' => ['referral_qualified']],
                'components' => [['type' => 'points', 'points' => 15, 'unit' => 'pts']],
            ],
            '2026-06-01T00:00:00Z',
            'referee-join',
        ),
    );
    OwnerContext::withOwner($owner, function () use ($refereeProgram, $refereeVersion, $invitee, $recipientType, $start): void {
        TransitionRewardProgramStatusAction::run($refereeProgram, RewardProgramStatus::Ready);
        TransitionRewardProgramStatusAction::run($refereeProgram, RewardProgramStatus::Active);
        EnrollRewardMemberAction::run(
            $refereeProgram,
            $recipientType,
            (string) $invitee->getKey(),
            $refereeVersion,
            'member-portal',
            $start,
        );
    });

    $mismatched = OwnerContext::withOwner($owner, fn () => FulfillReferralReward::run($refereeReward));

    expect($mismatched->status)->toBe(ReferralRewardStatus::Failed)
        ->and($mismatched->metadata['fulfillment_failure'])->toContain('does not match the frozen referral points spec')
        ->and(RewardAward::query()->where('source_business_key', $refereeBusinessKey)->count())->toBe(0);

    // The host approves and fulfils the referral side's rewards award, so
    // the referrer's points are spendable before the referral reverses.
    OwnerContext::withOwner($owner, function () use ($referrerAward, $staff, $start): void {
        TransitionRewardAwardAction::run($referrerAward, RewardAwardStatus::Approved, 'user', (string) $staff->getKey(), 'staff', 'approve', null, null, $start);
        TransitionRewardAwardAction::run($referrerAward, RewardAwardStatus::Fulfilled, 'user', (string) $staff->getKey(), 'staff', 'fulfil', null, null, $start);
    });

    $asOf = CarbonImmutable::now('UTC')->addMinute();
    $spendableBefore = RewardPointLedger::balancesFor($recipientType, (string) $referrer->getKey(), 'referral-share', 'pts', $asOf);

    expect($spendableBefore->available)->toBe(10);

    // The same source key can carry a milestone contribution whose cycle has
    // no program owner. The correction sweep records that contribution error,
    // but it must still claw back this referral side's award successfully.
    $orphanCycle = RewardMilestoneCycle::create([
        'recipient_type' => $recipientType,
        'recipient_id' => (string) $referrer->getKey(),
        'family_key' => 'orphan-cycle-family',
        'program_id' => null,
        'milestone_key' => 'orphan-cycle-milestone',
        'window_start' => $start,
        'window_days' => 30,
        'target_count' => 2,
        'current_count' => 1,
        'reward_snapshot' => ['type' => 'points', 'points' => 10, 'unit' => 'pts'],
        'status' => RewardMilestoneStatus::Open,
    ]);
    $orphanContribution = RewardMilestoneContribution::create([
        'cycle_id' => (string) $orphanCycle->getKey(),
        'source_business_key' => $businessKey,
        'contributor_type' => $recipientType,
        'contributor_id' => (string) $referrer->getKey(),
        'contributor_role' => 'sharer',
        'outcome' => 'referral_qualified',
        'source_snapshot' => ['source_business_key' => $businessKey],
        'occurred_at' => $start,
        'contributed_at' => $start,
    ]);

    $throwingSweep = Mockery::mock(new ApplyRewardSourceCorrectionsAction);
    $throwingSweep->shouldReceive('handle')->andThrow(new RuntimeException('simulated sweep outage'));
    app()->instance(ApplyRewardSourceCorrectionsAction::class, $throwingSweep);

    expect(fn () => OwnerContext::withOwner($owner, fn () => ReverseReferralReward::run($fulfilled, 'host confirmed reversal')))
        ->toThrow(ReferralFulfillmentException::class, 'could not be driven');

    expect($fulfilled->fresh()->status)->toBe(ReferralRewardStatus::Fulfilled)
        ->and($referrerAward->fresh()->status)->toBe(RewardAwardStatus::Fulfilled)
        ->and($fulfilled->fresh()->metadata['rewards_reversal'] ?? null)->toBeNull();

    app()->instance(ApplyRewardSourceCorrectionsAction::class, new ApplyRewardSourceCorrectionsAction);

    $reversed = OwnerContext::withOwner($owner, fn () => ReverseReferralReward::run($fulfilled, 'host confirmed reversal'));

    // The reversal drives and verifies the rewards correction: the award is
    // no longer live and the spendable balance excludes the clawed-back
    // points.
    expect($reversed->status)->toBe(ReferralRewardStatus::Reversed)
        ->and($referrerAward->fresh()->status)->toBe(RewardAwardStatus::Reversed)
        ->and(RewardAward::query()
            ->where('source_business_key', $businessKey)
            ->whereIn('status', [RewardAwardStatus::Held, RewardAwardStatus::PendingReview, RewardAwardStatus::Approved, RewardAwardStatus::Fulfilled])
            ->exists())->toBeFalse()
        ->and(RewardPointLedger::balancesFor($recipientType, (string) $referrer->getKey(), 'referral-share', 'pts', $asOf)->available)->toBe(0)
        ->and($reversed->metadata['rewards_reversal'])->toBe('verified_corrections_sweep')
        ->and($orphanContribution->fresh()->revoked_at)->toBeNull();
});

it('blocks the referral reversal when the rewards award cannot be reversed', function (): void {
    $start = CarbonImmutable::parse('2026-06-15T12:00:00Z');
    CarbonImmutable::setTestNow($start);
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'points-blocked@example.com']);
    $staff = User::factory()->create();
    $recipientType = $referrer->getMorphClass();
    config()->set('rewards.outcomes', ['referral_qualified']);
    config()->set('rewards.recipient_types', [$recipientType]);

    $referralProgram = $this->newProgram($owner, [
        'referrer_reward' => ['driver' => 'reward_points', 'points' => 10, 'family_key' => 'referral-share'],
        'referee_reward' => null,
    ]);
    $created = $this->newInvite($owner, $referralProgram, $referrer, $invitee->email);
    $accepted = $this->acceptInvite($owner, (string) $created->token, $invitee, $invitee->email);
    $qualified = OwnerContext::withOwner(
        $owner,
        fn () => QualifyReferral::run($accepted, 'registration:points-blocked'),
    );
    $reward = $qualified->rewards[0];
    $businessKey = 'referrals:qualified:' . $reward->invite_id . ':referrer';

    $reader = new FakeRewardSourceFactReader;
    $reader->seed(RewardLedgerFixtures::fact(
        $businessKey,
        (string) $referrer->getKey(),
        '2026-06-12T00:00:00Z',
        'referral_qualified',
        ['actorId' => (string) $invitee->getKey()],
    ));
    app()->instance(RewardSourceFactReader::class, $reader);

    [$rewardsProgram, $version] = OwnerContext::withOwner(
        $owner,
        fn (): array => RewardLedgerFixtures::seedVersionedProgram(
            'referral-blocked',
            RewardProgramMode::Points,
            ['eligibility' => ['outcomes' => ['referral_qualified']]],
            '2026-06-01T00:00:00Z',
            'referral-share',
        ),
    );
    OwnerContext::withOwner($owner, function () use ($rewardsProgram, $version, $referrer, $recipientType, $start): void {
        TransitionRewardProgramStatusAction::run($rewardsProgram, RewardProgramStatus::Ready);
        TransitionRewardProgramStatusAction::run($rewardsProgram, RewardProgramStatus::Active);
        EnrollRewardMemberAction::run(
            $rewardsProgram,
            $recipientType,
            (string) $referrer->getKey(),
            $version,
            'member-portal',
            $start,
        );
    });

    $fulfilled = OwnerContext::withOwner($owner, fn () => FulfillReferralReward::run($reward));

    expect($fulfilled->status)->toBe(ReferralRewardStatus::Fulfilled);

    $award = RewardAward::query()
        ->where('source_business_key', $businessKey)
        ->where('recipient_type', $recipientType)
        ->where('recipient_id', $referrer->getKey())
        ->where('component', 'points')
        ->firstOrFail();

    OwnerContext::withOwner($owner, function () use ($award, $staff, $start): void {
        TransitionRewardAwardAction::run($award, RewardAwardStatus::Approved, 'user', (string) $staff->getKey(), 'staff', 'approve', null, null, $start);
        TransitionRewardAwardAction::run($award, RewardAwardStatus::Fulfilled, 'user', (string) $staff->getKey(), 'staff', 'fulfil', null, null, $start);
    });

    // An active reservation with unknown external status: the default null
    // resolver attests nothing, so the awards reversal refuses.
    OwnerContext::withOwner($owner, function () use ($award, $referrer, $recipientType, $start): void {
        ReserveRewardPointsAction::run(
            $recipientType,
            (string) $referrer->getKey(),
            $award->family_key,
            'pts',
            10,
            'rsv:referral-blocked-1',
            'hold',
            $start,
        );
        MarkRewardReservationSubmittedAction::run(
            'rsv:referral-blocked-1',
            RewardPointReservationSubmission::STATUS_SENDING,
            'ext-referral-blocked-1',
        );
    });

    expect(fn () => OwnerContext::withOwner($owner, fn () => ReverseReferralReward::run($fulfilled, 'fraud review')))
        ->toThrow(ReferralFulfillmentException::class);

    expect($fulfilled->fresh()->status)->toBe(ReferralRewardStatus::Fulfilled)
        ->and($award->fresh()->status)->toBe(RewardAwardStatus::Fulfilled)
        ->and($award->fresh()->reversed_at)->toBeNull();
});

it('fulfils the referrer side only from the family-bound rewards program', function (): void {
    $start = CarbonImmutable::parse('2026-06-15T12:00:00Z');
    CarbonImmutable::setTestNow($start);
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'points-family@example.com']);
    $recipientType = $referrer->getMorphClass();
    config()->set('rewards.outcomes', ['referral_qualified']);
    config()->set('rewards.recipient_types', [$recipientType]);

    $referralProgram = $this->newProgram($owner, [
        'referrer_reward' => ['driver' => 'reward_points', 'points' => 10, 'family_key' => 'correct-family'],
        'referee_reward' => null,
    ]);
    $created = $this->newInvite($owner, $referralProgram, $referrer, $invitee->email);
    $accepted = $this->acceptInvite($owner, (string) $created->token, $invitee, $invitee->email);
    $qualified = OwnerContext::withOwner(
        $owner,
        fn () => QualifyReferral::run($accepted, 'registration:points-family'),
    );
    $referrerReward = $qualified->rewards[0];
    $businessKey = 'referrals:qualified:' . $referrerReward->invite_id . ':referrer';

    $reader = new FakeRewardSourceFactReader;
    $reader->seed(RewardLedgerFixtures::fact(
        $businessKey,
        (string) $referrer->getKey(),
        '2026-06-12T00:00:00Z',
        'referral_qualified',
        ['actorId' => (string) $invitee->getKey()],
    ));
    app()->instance(RewardSourceFactReader::class, $reader);

    // Two active programs both pay 10 points; only the spec-bound family may
    // create the fulfilling award.
    foreach (['wrong-family', 'correct-family'] as $family) {
        [$program, $version] = OwnerContext::withOwner(
            $owner,
            fn (): array => RewardLedgerFixtures::seedVersionedProgram(
                'family-' . $family,
                RewardProgramMode::Points,
                ['eligibility' => ['outcomes' => ['referral_qualified']]],
                '2026-06-01T00:00:00Z',
                $family,
            ),
        );
        OwnerContext::withOwner($owner, function () use ($program, $version, $referrer, $recipientType, $start): void {
            TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Ready);
            TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Active);
            EnrollRewardMemberAction::run(
                $program,
                $recipientType,
                (string) $referrer->getKey(),
                $version,
                'member-portal',
                $start,
            );
        });
    }

    $fulfilled = OwnerContext::withOwner($owner, fn () => FulfillReferralReward::run($referrerReward));
    $awards = RewardAward::query()->where('source_business_key', $businessKey)->get();

    expect($fulfilled->status)->toBe(ReferralRewardStatus::Fulfilled)
        ->and($awards)->toHaveCount(1)
        ->and($awards->first()->family_key)->toBe('correct-family')
        ->and($awards->first()->beneficiary_role)->toBe(RewardBeneficiaryRole::Sharer)
        ->and(RewardAward::query()->where('family_key', 'wrong-family')->count())->toBe(0);
});

it('keeps a global rewards program outside tenant referral fulfilment by default', function (): void {
    $start = CarbonImmutable::parse('2026-06-15T12:00:00Z');
    CarbonImmutable::setTestNow($start);
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'global-points@example.com']);
    $recipientType = $referrer->getMorphClass();

    expect(config('rewards.owner.include_global'))->toBeFalse();

    config()->set('rewards.outcomes', ['referral_qualified']);
    config()->set('rewards.recipient_types', [$recipientType]);

    $referralProgram = $this->newProgram($owner, [
        'referrer_reward' => ['driver' => 'reward_points', 'points' => 10, 'family_key' => 'global-referral-points'],
        'referee_reward' => null,
    ]);
    $created = $this->newInvite($owner, $referralProgram, $referrer, $invitee->email);
    $accepted = $this->acceptInvite($owner, (string) $created->token, $invitee, $invitee->email);
    $qualified = OwnerContext::withOwner(
        $owner,
        fn () => QualifyReferral::run($accepted, 'registration:global-points'),
    );
    $reward = $qualified->rewards[0];
    $businessKey = 'referrals:qualified:' . $reward->invite_id . ':referrer';

    $reader = new FakeRewardSourceFactReader;
    $reader->seed(RewardLedgerFixtures::fact(
        $businessKey,
        (string) $referrer->getKey(),
        '2026-06-12T00:00:00Z',
        'referral_qualified',
        ['actorId' => (string) $invitee->getKey()],
    ));
    app()->instance(RewardSourceFactReader::class, $reader);

    [$globalProgram, $version] = OwnerContext::withOwner(
        null,
        fn (): array => RewardLedgerFixtures::seedVersionedProgram(
            'global-referral',
            RewardProgramMode::Points,
            ['eligibility' => ['outcomes' => ['referral_qualified']]],
            '2026-06-01T00:00:00Z',
            'global-referral-points',
        ),
    );
    OwnerContext::withOwner(null, function () use ($globalProgram, $version, $referrer, $recipientType, $start): void {
        TransitionRewardProgramStatusAction::run($globalProgram, RewardProgramStatus::Ready);
        TransitionRewardProgramStatusAction::run($globalProgram, RewardProgramStatus::Active);
        EnrollRewardMemberAction::run(
            $globalProgram,
            $recipientType,
            (string) $referrer->getKey(),
            $version,
            'member-portal',
            $start,
        );
    });

    $failed = OwnerContext::withOwner($owner, fn () => FulfillReferralReward::run($reward));

    expect($globalProgram->owner_type)->toBeNull()
        ->and($globalProgram->owner_id)->toBeNull()
        ->and($failed->status)->toBe(ReferralRewardStatus::Failed)
        ->and($failed->metadata['fulfillment_failure'])->toContain('without creating')
        ->and(RewardAward::query()->where('source_business_key', $businessKey)->count())->toBe(0);
});

it('rejects differing points on a shared or missing rewards family', function (): void {
    $owner = $this->newOwner();

    expect(fn () => $this->newProgram($owner, [
        'referrer_reward' => ['driver' => 'reward_points', 'points' => 10],
        'referee_reward' => ['driver' => 'reward_points', 'points' => 20],
    ]))->toThrow(InvalidArgumentException::class, 'family_key');

    expect(fn () => $this->newProgram($owner, [
        'referrer_reward' => ['driver' => 'reward_points', 'points' => 10, 'family_key' => 'shared-family'],
        'referee_reward' => ['driver' => 'reward_points', 'points' => 20, 'family_key' => 'shared-family'],
    ]))->toThrow(InvalidArgumentException::class, 'family_key');

    $distinctFamilies = $this->newProgram($owner, [
        'referrer_reward' => ['driver' => 'reward_points', 'points' => 10, 'family_key' => 'share-side'],
        'referee_reward' => ['driver' => 'reward_points', 'points' => 20, 'family_key' => 'join-side'],
    ]);

    expect($distinctFamilies->referrer_reward['family_key'])->toBe('share-side')
        ->and($distinctFamilies->referee_reward['family_key'])->toBe('join-side');

    $equalPoints = $this->newProgram($owner, [
        'referrer_reward' => ['driver' => 'reward_points', 'points' => 10],
        'referee_reward' => ['driver' => 'reward_points', 'points' => 10],
    ]);

    expect($equalPoints->referee_reward['points'])->toBe(10);
});

it('accepts a points family slug and rejects family keys on other drivers', function (): void {
    $validator = app(ReferralRewardSpecValidator::class);

    $spec = $validator->validate(['driver' => 'reward_points', 'points' => 10, 'family_key' => 'referral-share']);

    expect($spec['family_key'])->toBe('referral-share');

    expect(fn (): ?array => $validator->validate(['driver' => 'manual', 'family_key' => 'referral-share']))
        ->toThrow(InvalidArgumentException::class);
    expect(fn (): ?array => $validator->validate(['driver' => 'voucher', 'voucher' => ['type' => 'credit', 'value' => 500], 'family_key' => 'referral-share']))
        ->toThrow(InvalidArgumentException::class);
    expect(fn (): ?array => $validator->validate(['driver' => 'reward_points', 'points' => 10, 'family_key' => 'Bad Slug']))
        ->toThrow(InvalidArgumentException::class);
    expect(fn (): ?array => $validator->validate(['driver' => 'reward_points', 'points' => 10, 'family_key' => '']))
        ->toThrow(InvalidArgumentException::class);
    expect(fn (): ?array => $validator->validate(['driver' => 'reward_points', 'points' => 10, 'family_key' => str_repeat('a', 65)]))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects voucher specs that are not single-use or are mispriced', function (): void {
    $owner = $this->newOwner();

    $program = static fn (array $voucher): array => [
        'referrer_reward' => [
            'driver' => 'voucher',
            'amount_minor' => 2500,
            'currency' => 'MYR',
            'voucher' => $voucher,
        ],
        'referee_reward' => null,
    ];

    expect(fn () => $this->newProgram($owner, $program(['type' => 'fixed', 'value' => 2500, 'usage_limit' => 5])))
        ->toThrow(InvalidArgumentException::class, 'single-use');
    expect(fn () => $this->newProgram($owner, $program(['type' => 'fixed', 'value' => 2500, 'max_uses' => 2])))
        ->toThrow(InvalidArgumentException::class, 'single-use');
    expect(fn () => $this->newProgram($owner, $program(['type' => 'fixed', 'value' => 2500, 'usage_limit_per_user' => 3])))
        ->toThrow(InvalidArgumentException::class, 'single-use');
    expect(fn () => $this->newProgram($owner, $program(['type' => 'fixed', 'value' => 2500, 'max_uses_per_user' => 0])))
        ->toThrow(InvalidArgumentException::class, 'single-use');
    expect(fn () => $this->newProgram($owner, $program(['type' => 'fixed', 'value' => 1000])))
        ->toThrow(InvalidArgumentException::class, 'amount_minor');
    expect(fn () => $this->newProgram($owner, $program(['type' => 'fixed', 'value' => 2500, 'metadata' => ['assignee' => ['type' => 'x', 'id' => 'y']]])))
        ->toThrow(InvalidArgumentException::class, 'assignee');

    $accepted = $this->newProgram($owner, $program(['type' => 'fixed', 'usage_limit' => 1, 'usage_limit_per_user' => 1]));

    expect($accepted->referrer_reward['voucher']['value'])->toBe(2500);
});

it('issues single-use beneficiary-bound vouchers and refuses foreign redemption', function (): void {
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'voucher-rail@example.com']);
    $program = $this->newProgram($owner, [
        'referrer_reward' => [
            'driver' => 'voucher',
            'amount_minor' => 2500,
            'currency' => 'MYR',
            'voucher' => ['type' => 'fixed'],
        ],
        'referee_reward' => null,
    ]);
    $created = $this->newInvite($owner, $program, $referrer, $invitee->email);
    $accepted = $this->acceptInvite($owner, (string) $created->token, $invitee, $invitee->email);
    $qualified = OwnerContext::withOwner($owner, fn () => QualifyReferral::run($accepted, 'registration:voucher-rail'));
    $reward = $qualified->rewards[0];

    $fulfilled = OwnerContext::withOwner($owner, fn () => FulfillReferralReward::run($reward));

    expect($fulfilled->status)->toBe(ReferralRewardStatus::Fulfilled);

    $code = (string) $fulfilled->external_reference;
    $voucher = Voucher::query()->where('code', $code)->firstOrFail();

    // The adapter forces single use, the frozen value, and the frozen
    // beneficiary as the assignee marker the vouchers package enforces.
    expect($voucher->usage_limit)->toBe(1)
        ->and($voucher->usage_limit_per_user)->toBe(1)
        ->and((int) $voucher->value)->toBe(2500)
        ->and($voucher->metadata['assignee']['type'])->toBe($referrer->getMorphClass())
        ->and($voucher->metadata['assignee']['id'])->toBe((string) $referrer->getKey());

    $replay = OwnerContext::withOwner($owner, fn () => FulfillReferralReward::run($fulfilled));

    expect($replay->external_reference)->toBe($code)
        ->and(Voucher::query()->count())->toBe(1);

    $other = User::factory()->create();

    Auth::login($referrer);

    expect(ValidateVoucherCode::run($code, ['total' => 100000])->isValid)->toBeTrue();

    Auth::login($other);

    expect(ValidateVoucherCode::run($code, ['total' => 100000])->isValid)->toBeFalse();

    expect(fn () => RecordVoucherUsage::run($code, Money::MYR(2500), null, null, $other))
        ->toThrow(InvalidVoucherException::class);
});

it('authorizes affiliates policies only for the exact pending referral reward', function (): void {
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'affiliate-policy@example.com']);
    $program = $this->newProgram($owner, [
        'referrer_reward' => ['driver' => 'affiliate_cash', 'amount_minor' => 1250, 'currency' => 'MYR'],
        'referee_reward' => null,
    ]);
    $created = $this->newInvite($owner, $program, $referrer, $invitee->email);
    $accepted = $this->acceptInvite($owner, (string) $created->token, $invitee, $invitee->email);
    $qualified = OwnerContext::withOwner($owner, fn () => QualifyReferral::run($accepted, 'registration:affiliate-policy'));
    $reward = $qualified->rewards[0];
    $recipient = new Affiliate;
    $recipient->setAttribute('id', 'fixture-affiliate');
    $resolver = new class($recipient) implements ReferralAffiliateResolver
    {
        public function __construct(private readonly Affiliate $affiliate) {}

        public function resolve(ReferralReward $reward): ?object
        {
            return $this->affiliate;
        }
    };
    $policy = new ReferralAffiliateAwardPolicy(app(ReferralProgramGuard::class), $resolver);
    $key = 'referrals:award:' . $reward->invite_id . ':referrer';
    $proposal = new AffiliateAwardProposal($key, 1250, 'MYR', ReferralRewardStatus::class, 'registration:affiliate-policy', (string) $reward->invite_id);

    $decision = OwnerContext::withOwner($owner, fn (): AffiliateAwardDecision => $policy->decide($recipient, $proposal));
    $wrongAmount = OwnerContext::withOwner($owner, fn (): AffiliateAwardDecision => $policy->decide(
        $recipient,
        new AffiliateAwardProposal($key, 1300, 'MYR', ReferralRewardStatus::class, 'registration:affiliate-policy', (string) $reward->invite_id),
    ));

    expect($decision->authorized)->toBeTrue()
        ->and($decision->policyMinMinor)->toBe(1250)
        ->and($decision->policyMaxMinor)->toBe(1250)
        ->and($wrongAmount->authorized)->toBeFalse();
});

it('delegates fulfilment and reversal to the bound rail only once', function (): void {
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'fake-rail@example.com']);
    $program = $this->newProgram($owner, ['referee_reward' => null]);
    $created = $this->newInvite($owner, $program, $referrer, $invitee->email);
    $accepted = $this->acceptInvite($owner, (string) $created->token, $invitee, $invitee->email);
    $qualified = OwnerContext::withOwner($owner, fn () => QualifyReferral::run($accepted, 'registration:fake-rail'));
    $rail = new class implements ReferralRewardFulfillment
    {
        public int $fulfilCalls = 0;

        public int $reverseCalls = 0;

        public function fulfill(ReferralReward $reward): ReferralFulfillmentResult
        {
            $this->fulfilCalls++;

            return ReferralFulfillmentResult::fulfilled('fake-rail-reference');
        }

        public function reverse(ReferralReward $reward, string $reason): ReferralFulfillmentResult
        {
            $this->reverseCalls++;

            return ReferralFulfillmentResult::fulfilled($reward->external_reference);
        }
    };
    app()->instance(ReferralRewardFulfillment::class, $rail);

    $reward = $qualified->rewards[0];
    $fulfilled = OwnerContext::withOwner($owner, fn () => FulfillReferralReward::run($reward));
    $fulfilledReplay = OwnerContext::withOwner($owner, fn () => FulfillReferralReward::run($fulfilled));
    $reversed = OwnerContext::withOwner($owner, fn () => ReverseReferralReward::run($fulfilledReplay, 'host confirmed reversal'));
    $reversedReplay = OwnerContext::withOwner($owner, fn () => ReverseReferralReward::run($reversed, 'host confirmed reversal'));

    expect($rail->fulfilCalls)->toBe(1)
        ->and($rail->reverseCalls)->toBe(1)
        ->and($fulfilledReplay->external_reference)->toBe('fake-rail-reference')
        ->and($reversedReplay->status)->toBe(ReferralRewardStatus::Reversed);
});

it('expires due invites and leaves future invites pending', function (): void {
    $start = CarbonImmutable::parse('2026-03-01 12:00:00 UTC');
    CarbonImmutable::setTestNow($start);
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $dueProgram = $this->newProgram($owner, ['invite_expiry_days' => 1]);
    $futureProgram = $this->newProgram($owner, ['invite_expiry_days' => 30]);
    $dueInvitee = User::factory()->create(['email' => 'due@example.com']);
    $futureInvitee = User::factory()->create(['email' => 'future@example.com']);
    $due = $this->newInvite($owner, $dueProgram, $referrer, $dueInvitee->email);
    CarbonImmutable::setTestNow($start->addDays(1));
    $future = $this->newInvite($owner, $futureProgram, $referrer, $futureInvitee->email);
    CarbonImmutable::setTestNow($start->addDays(2));

    Artisan::call('referrals:expire-invites');

    expect($due->invite->fresh()->status)->toBe(ReferralInviteStatus::Expired)
        ->and($future->invite->fresh()->status)->toBe(ReferralInviteStatus::Pending);
});

it('expires due invites across tenants when referral owner scoping is disabled', function (): void {
    $start = CarbonImmutable::parse('2026-04-01 12:00:00 UTC');
    CarbonImmutable::setTestNow($start);

    $ownerA = $this->newOwner();
    $ownerB = $this->newOwner();
    $referrer = User::factory()->create();
    $programA = $this->newProgram($ownerA, ['invite_expiry_days' => 1]);
    $programB = $this->newProgram($ownerB, ['invite_expiry_days' => 1]);
    $inviteA = $this->newInvite($ownerA, $programA, $referrer, 'owner-disabled-a@example.com')->invite;
    $inviteB = $this->newInvite($ownerB, $programB, $referrer, 'owner-disabled-b@example.com')->invite;

    CarbonImmutable::setTestNow($start->addDays(2));
    config(['referrals.owner.enabled' => false]);
    Model::clearBootedModels();

    $exitCode = Artisan::call('referrals:expire-invites');

    expect($exitCode)->toBe(0)
        ->and($inviteA->fresh()->status)->toBe(ReferralInviteStatus::Expired)
        ->and($inviteB->fresh()->status)->toBe(ReferralInviteStatus::Expired);
});

/**
 * Builds a global (platform-owned) program with one invite under the
 * explicit-global context, mirroring the tenant helpers.
 */
function globalReferralProgram(string $slug): ReferralProgram
{
    return OwnerContext::withOwner(null, fn (): ReferralProgram => CreateReferralProgram::run([
        'name' => 'Global referral',
        'slug' => $slug,
        'status' => 'active',
        'qualifier_outcome' => 'first_registration',
        'currency' => 'MYR',
        'referrer_reward' => ['driver' => 'manual'],
        'referee_reward' => ['driver' => 'manual'],
    ]));
}

function globalReferralInvite(ReferralProgram $program, User $referrer, string $inviteeEmail): ReferralInviteCreationResult
{
    return OwnerContext::withOwner(null, fn (): ReferralInviteCreationResult => CreateReferralInvite::run($program, $referrer, $inviteeEmail));
}

it('refuses tenant writes on global programs while global reads stay visible', function (): void {
    config(['referrals.owner.include_global' => true]);

    $tenant = $this->newOwner();
    $referrer = User::factory()->create();
    $qualifiedInvitee = User::factory()->create(['email' => 'global-qualifies@example.com']);
    $revocableInvitee = User::factory()->create(['email' => 'global-revokes@example.com']);
    $suffix = bin2hex(random_bytes(4));

    $program = globalReferralProgram('global-write-' . $suffix);

    // Two lives on the global program: one qualified (rewards minted), one
    // merely accepted (revocable).
    $qualifiedCreated = globalReferralInvite($program, $referrer, $qualifiedInvitee->email);
    $qualified = OwnerContext::withOwner(null, fn (): ReferralInvite => AcceptReferralInvite::run((string) $qualifiedCreated->token, $qualifiedInvitee, $qualifiedInvitee->email));
    $qualified = OwnerContext::withOwner(null, fn (): array => QualifyReferral::run($qualified, 'registration:global-write-' . $suffix)->rewards);
    $reward = $qualified[0];

    $revocableCreated = globalReferralInvite($program, $referrer, $revocableInvitee->email);
    $revocable = OwnerContext::withOwner(null, fn (): ReferralInvite => AcceptReferralInvite::run((string) $revocableCreated->token, $revocableInvitee, $revocableInvitee->email));

    // Visibility: include_global still lets a tenant SEE the global program.
    OwnerContext::withOwner($tenant, function () use ($program): void {
        expect(ReferralProgram::query()->whereKey($program->getKey())->exists())->toBeTrue();
    });

    // Writes: the guard resolves for writes with includeGlobal=false, so a
    // tenant context cannot fulfil a global-program reward or revoke a
    // global-program invite.
    OwnerContext::withOwner($tenant, function () use ($reward, $revocable): void {
        expect(fn () => FulfillReferralReward::run($reward))->toThrow(AuthorizationException::class);
        expect(fn () => RevokeReferralInvite::run($revocable))->toThrow(AuthorizationException::class);
    });

    expect($reward->fresh()->status)->toBe(ReferralRewardStatus::Pending)
        ->and($revocable->fresh()->status)->toBe(ReferralInviteStatus::Accepted);

    // Explicit-global context still mutates global rows. The manual driver
    // requires a stored reference before it can fulfil.
    OwnerContext::withOwner(null, static function () use ($reward): void {
        $reward->metadata = ['external_reference' => 'global-receipt-1'];
        $reward->save();
    });

    OwnerContext::withOwner(null, fn () => FulfillReferralReward::run($reward));
    OwnerContext::withOwner(null, fn () => RevokeReferralInvite::run($revocable));

    expect($reward->fresh()->status)->toBe(ReferralRewardStatus::Fulfilled)
        ->and($revocable->fresh()->status)->toBe(ReferralInviteStatus::Revoked);
});

it('expires due invites per owner across tenants and the global program', function (): void {
    $start = CarbonImmutable::parse('2026-05-01 12:00:00 UTC');
    $horizon = $start->addDays(2); // the command's "now"
    CarbonImmutable::setTestNow($start);

    $ownerA = $this->newOwner();
    $ownerB = $this->newOwner();
    $referrer = User::factory()->create();

    // Tenant A, tenant B, and the global program each own a due program and
    // a future program. Only the due invites may expire. Everything a given
    // owner creates stays inside that owner's context. Both invites of a pair
    // are created at $start so the clock never walks between owners: the due
    // program carries a one-day window (deadline $start + 1d, already past the
    // $horizon) and the future program a thirty-day window (deadline still
    // ahead of $horizon).
    $seedPair = static function (?User $owner, string $label, string $dueEmail, string $futureEmail) use ($referrer): array {
        $attributes = static fn (int $days): array => [
            'name' => $label . ' referral',
            'slug' => $label . '-' . $days . '-' . bin2hex(random_bytes(3)),
            'status' => 'active',
            'qualifier_outcome' => 'first_registration',
            'invite_expiry_days' => $days,
            'currency' => 'MYR',
            'referrer_reward' => ['driver' => 'manual'],
            'referee_reward' => ['driver' => 'manual'],
        ];

        $due = OwnerContext::withOwner(
            $owner,
            static fn (): ReferralInviteCreationResult => CreateReferralInvite::run(
                CreateReferralProgram::run($attributes(1)),
                $referrer,
                $dueEmail,
            ),
        );

        $future = OwnerContext::withOwner(
            $owner,
            static fn (): ReferralInviteCreationResult => CreateReferralInvite::run(
                CreateReferralProgram::run($attributes(30)),
                $referrer,
                $futureEmail,
            ),
        );

        return [$due, $future];
    };

    [$dueA, $futureA] = $seedPair($ownerA, 'expire-a', 'expire-a-due@example.com', 'expire-a-future@example.com');

    [$dueB, $futureB] = $seedPair($ownerB, 'expire-b', 'expire-b-due@example.com', 'expire-b-future@example.com');

    [$dueGlobal, $futureGlobal] = $seedPair(null, 'expire-g', 'expire-g-due@example.com', 'expire-g-future@example.com');

    CarbonImmutable::setTestNow($horizon);

    $exitCode = Artisan::call('referrals:expire-invites');

    expect($exitCode)->toBe(0)
        ->and($dueA->invite->fresh()->status)->toBe(ReferralInviteStatus::Expired)
        ->and($futureA->invite->fresh()->status)->toBe(ReferralInviteStatus::Pending)
        ->and($dueB->invite->fresh()->status)->toBe(ReferralInviteStatus::Expired)
        ->and($futureB->invite->fresh()->status)->toBe(ReferralInviteStatus::Pending)
        ->and($dueGlobal->invite->fresh()->status)->toBe(ReferralInviteStatus::Expired)
        ->and($futureGlobal->invite->fresh()->status)->toBe(ReferralInviteStatus::Pending);
});

it('suppresses include_global inside every per-owner expire pass', function (): void {
    $start = CarbonImmutable::parse('2026-05-01 12:00:00 UTC');
    $horizon = $start->addDays(2);
    CarbonImmutable::setTestNow($start);

    // A host that opts into global read visibility. Each per-owner pass is a
    // WRITE pass, so it must not inherit that visibility: the guard resolves
    // writes with includeGlobal=false, so a pass that scans a global program
    // would hand the guard a row it refuses and abort the whole scheduled
    // sweep part-way through the tenant list.
    config(['referrals.owner.include_global' => true]);

    $owner = $this->newOwner();
    $referrer = User::factory()->create();

    $seedDueInvite = static function (?User $owner, string $label, string $email) use ($referrer): ReferralInvite {
        return OwnerContext::withOwner(
            $owner,
            static fn (): ReferralInviteCreationResult => CreateReferralInvite::run(
                CreateReferralProgram::run([
                    'name' => $label . ' referral',
                    'slug' => $label . '-' . bin2hex(random_bytes(3)),
                    'status' => 'active',
                    'qualifier_outcome' => 'first_registration',
                    'invite_expiry_days' => 1,
                    'currency' => 'MYR',
                    'referrer_reward' => ['driver' => 'manual'],
                    'referee_reward' => ['driver' => 'manual'],
                ]),
                $referrer,
                $email,
            ),
        )->invite;
    };

    $tenantInvite = $seedDueInvite($owner, 'expire-scope-tenant', 'expire-scope-tenant@example.com');
    $globalInvite = $seedDueInvite(null, 'expire-scope-global', 'expire-scope-global@example.com');

    // Every invite the sweep writes is written from inside a per-owner pass,
    // so recording the override state on each expiry update samples exactly
    // the windows that must be suppressed. (The tuple order the enumeration
    // happens to produce is not part of the contract: the global pass would
    // otherwise mask a tenant pass that still sees the global program.)
    $suppressionWhileExpiring = [];

    ReferralInvite::updating(function () use (&$suppressionWhileExpiring): void {
        $suppressionWhileExpiring[] = OwnerScopeOverride::suppressIncludeGlobal();
    });

    CarbonImmutable::setTestNow($horizon);

    $exitCode = Artisan::call('referrals:expire-invites');

    expect($exitCode)->toBe(0)
        ->and($tenantInvite->fresh()->status)->toBe(ReferralInviteStatus::Expired)
        ->and($globalInvite->fresh()->status)->toBe(ReferralInviteStatus::Expired)
        // One expiry per pass, and no pass may run with include-global live.
        ->and($suppressionWhileExpiring)->toHaveCount(2)
        ->and(array_filter($suppressionWhileExpiring, static fn (bool $suppressed): bool => ! $suppressed))->toBeEmpty();

    // The host's config is untouched: suppression is request-scoped, not a
    // config flip that an Octane worker could leak into the next request.
    expect(config('referrals.owner.include_global'))->toBeTrue();
});

it('stores only the invite token hash and encrypts the addressed contact', function (): void {
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $program = $this->newProgram($owner);
    $created = $this->newInvite($owner, $program, $referrer, ' Stored@Example.com ');
    $persisted = DB::table('referral_invites')->where('id', $created->invite->getKey())->first();
    $normalizedContact = app(ReferralContactNormalizer::class)->normalize(' Stored@Example.com ');

    expect($created->token)->toBeString()
        ->and($persisted->token_hash)->not->toBe($created->token)
        ->and($persisted->token_hash)->toBe(hash('sha256', $created->token))
        ->and($persisted->invitee_contact_encrypted)->not->toBe($normalizedContact)
        ->and($created->invite->getAttribute('invitee_contact_encrypted'))->toBe($normalizedContact);
});

it('rejects an invalid program edit and leaves the stored program unchanged', function (): void {
    $owner = $this->newOwner();
    $program = $this->newProgram($owner, [
        'currency' => 'MYR',
        'starts_at' => '2026-01-01T00:00:00Z',
        'ends_at' => '2026-02-01T00:00:00Z',
    ]);

    // Identical rules to creation: an edit cannot smuggle in a currency that
    // creation would have refused.
    expect(fn (): ReferralProgram => OwnerContext::withOwner(
        $owner,
        fn (): ReferralProgram => UpdateReferralProgram::run($program, ['currency' => '!!!']),
    ))->toThrow(InvalidArgumentException::class);

    // A name that slugifies to nothing is rejected exactly like creation.
    expect(fn (): ReferralProgram => OwnerContext::withOwner(
        $owner,
        fn (): ReferralProgram => UpdateReferralProgram::run($program, ['name' => '!!!']),
    ))->toThrow(InvalidArgumentException::class);

    // A partial edit only supplies ends_at; the ordering check still merges
    // the stored starts_at, so an inverted window is refused.
    expect(fn (): ReferralProgram => OwnerContext::withOwner(
        $owner,
        fn (): ReferralProgram => UpdateReferralProgram::run($program, ['ends_at' => '2026-01-01T00:00:00Z']),
    ))->toThrow(InvalidArgumentException::class);

    $stored = $program->fresh();

    expect($stored->currency)->toBe('MYR')
        ->and($stored->name)->toBe('Member referral')
        ->and($stored->ends_at?->equalTo(CarbonImmutable::parse('2026-02-01T00:00:00Z')))->toBeTrue();
});

it('refuses to reactivate an archived program or rewrite its owner through the update action', function (): void {
    $owner = $this->newOwner();
    $program = $this->newProgram($owner, ['status' => 'archived']);

    // Lifecycle changes flow only through the matrix-checked status actions;
    // an update payload may never name a status.
    expect(fn (): ReferralProgram => OwnerContext::withOwner(
        $owner,
        fn (): ReferralProgram => UpdateReferralProgram::run($program, ['status' => 'active']),
    ))->toThrow(InvalidArgumentException::class);

    // Owner tuples are immutable after creation.
    expect(fn (): ReferralProgram => OwnerContext::withOwner(
        $owner,
        fn (): ReferralProgram => UpdateReferralProgram::run($program, ['owner_id' => 'foreign-owner']),
    ))->toThrow(InvalidArgumentException::class);

    expect($program->fresh()->status)->toBe(ReferralProgramStatus::Archived)
        ->and($program->fresh()->owner_id)->toBe($owner->getKey());
});

it('applies a valid program edit through the core update action', function (): void {
    $owner = $this->newOwner();
    $program = $this->newProgram($owner, [
        'name' => 'Member referral',
        'slug' => 'custom-member-slug',
        'invite_expiry_days' => 7,
        'metadata' => ['channel' => 'email'],
    ]);

    // An edit that does not touch the name keeps the stored slug, so an
    // unrelated change never rewrites the program's public identifier.
    $partial = OwnerContext::withOwner($owner, fn (): ReferralProgram => UpdateReferralProgram::run($program, [
        'invite_expiry_days' => 21,
        'ends_at' => '2027-01-01T00:00:00Z',
        'metadata' => ['channel' => 'sms'],
    ]));

    expect($partial->slug)->toBe('custom-member-slug')
        ->and($partial->name)->toBe('Member referral')
        ->and($partial->invite_expiry_days)->toBe(21)
        ->and($partial->currency)->toBe('MYR')
        ->and($partial->metadata)->toBe(['channel' => 'sms'])
        ->and($partial->starts_at)->toBeNull()
        ->and($partial->ends_at?->equalTo(CarbonImmutable::parse('2027-01-01T00:00:00Z')))->toBeTrue();

    $metadataPreserved = OwnerContext::withOwner($owner, fn (): ReferralProgram => UpdateReferralProgram::run($partial, [
        'max_invites_per_referrer' => 5,
    ]));

    expect($metadataPreserved->metadata)->toBe(['channel' => 'sms'])
        ->and($metadataPreserved->ends_at?->equalTo(CarbonImmutable::parse('2027-01-01T00:00:00Z')))->toBeTrue();

    $metadataCleared = OwnerContext::withOwner($owner, fn (): ReferralProgram => UpdateReferralProgram::run($metadataPreserved, [
        'metadata' => null,
    ]));

    expect($metadataCleared->metadata)->toBeNull();

    // A name-only edit re-derives the slug exactly as creation would.
    $renamed = OwnerContext::withOwner($owner, fn (): ReferralProgram => UpdateReferralProgram::run($metadataCleared, [
        'name' => 'Member referral v2',
    ]));

    expect($renamed->name)->toBe('Member referral v2')
        ->and($renamed->slug)->toBe('member-referral-v2')
        ->and($renamed->metadata)->toBeNull();
});

it('does not rewrite qualified reward specs when a program reward is edited', function (): void {
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'frozen-edit@example.com']);
    $program = $this->newProgram($owner, [
        'referrer_reward' => ['driver' => 'manual', 'amount_minor' => 500, 'currency' => 'MYR'],
        'referee_reward' => null,
    ]);
    $created = $this->newInvite($owner, $program, $referrer, $invitee->email);
    $accepted = $this->acceptInvite($owner, (string) $created->token, $invitee, $invitee->email);
    $qualified = OwnerContext::withOwner(
        $owner,
        fn () => QualifyReferral::run($accepted, 'registration:frozen-edit'),
    );
    $reward = $qualified->rewards[0];
    $frozenSpec = $reward->spec;

    $updated = OwnerContext::withOwner($owner, fn (): ReferralProgram => UpdateReferralProgram::run($program, [
        'referrer_reward' => ['driver' => 'manual', 'amount_minor' => 1500, 'currency' => 'MYR'],
    ]));

    expect($updated->referrer_reward['amount_minor'])->toBe(1500)
        ->and($reward->fresh()->spec)->toBe($frozenSpec)
        ->and($reward->fresh()->spec['amount_minor'])->toBe(500);
});

it('prevents another owner from updating a referral program', function (): void {
    $ownerA = $this->newOwner();
    $ownerB = $this->newOwner();
    $program = $this->newProgram($ownerA);

    expect(fn (): ReferralProgram => OwnerContext::withOwner(
        $ownerB,
        fn (): ReferralProgram => UpdateReferralProgram::run($program, ['name' => 'Hijacked referral']),
    ))->toThrow(AuthorizationException::class);

    expect($program->fresh()->name)->toBe('Member referral');
});

it('rejects configured non-default owner columns and status in an update payload', function (): void {
    config()->set('referrals.owner.owner_type_column', 'tenant_kind');
    config()->set('referrals.owner.owner_id_column', 'tenant_key');

    $normalizer = app(ReferralProgramAttributeNormalizer::class);
    $attributes = static fn (array $extra): array => array_replace([
        'name' => 'Member referral',
        'slug' => 'member-referral',
        'qualifier_outcome' => 'first_registration',
        'currency' => 'MYR',
    ], $extra);

    // Both the host-configured owner columns and the literal defaults stay
    // immutable, and status may only move through the status actions.
    expect(fn (): array => $normalizer->normalize($attributes(['tenant_key' => 'foreign']), forUpdate: true))
        ->toThrow(InvalidArgumentException::class, 'tenant_key');
    expect(fn (): array => $normalizer->normalize($attributes(['tenant_kind' => 'tenant']), forUpdate: true))
        ->toThrow(InvalidArgumentException::class, 'tenant_kind');
    expect(fn (): array => $normalizer->normalize($attributes(['owner_id' => 'foreign']), forUpdate: true))
        ->toThrow(InvalidArgumentException::class, 'owner_id');
    expect(fn (): array => $normalizer->normalize($attributes(['status' => 'active']), forUpdate: true))
        ->toThrow(InvalidArgumentException::class, 'status');

    // Creation still accepts a status and ignores owner keys.
    $created = $normalizer->normalize($attributes(['status' => 'active']));

    expect($created['status'])->toBe(ReferralProgramStatus::Active);
});
