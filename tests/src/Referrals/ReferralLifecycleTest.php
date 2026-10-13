<?php

declare(strict_types=1);

namespace Tests\src\Referrals;

use AIArmada\Affiliates\Data\AffiliateAwardDecision;
use AIArmada\Affiliates\Data\AffiliateAwardProposal;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Referrals\Actions\FulfillReferralReward;
use AIArmada\Referrals\Actions\QualifyReferral;
use AIArmada\Referrals\Actions\ReverseReferralReward;
use AIArmada\Referrals\Actions\RevokeReferralInvite;
use AIArmada\Referrals\Contracts\ReferralAffiliateResolver;
use AIArmada\Referrals\Contracts\ReferralRewardFulfillment;
use AIArmada\Referrals\Data\ReferralFulfillmentResult;
use AIArmada\Referrals\Enums\ReferralInviteStatus;
use AIArmada\Referrals\Enums\ReferralRewardStatus;
use AIArmada\Referrals\Exceptions\ReferralAlreadyClaimedException;
use AIArmada\Referrals\Exceptions\ReferralInviteNotFoundException;
use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Models\ReferralReward;
use AIArmada\Referrals\Support\ReferralAffiliateAwardPolicy;
use AIArmada\Referrals\Support\ReferralContactNormalizer;
use AIArmada\Referrals\Support\ReferralProgramGuard;
use AIArmada\Rewards\Actions\EnrollRewardMemberAction;
use AIArmada\Rewards\Actions\TransitionRewardProgramStatusAction;
use AIArmada\Rewards\Contracts\RewardSourceFactReader;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Enums\RewardProgramStatus;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Tests\Ledgers\Support\FakeRewardSourceFactReader;
use AIArmada\Rewards\Tests\Ledgers\Support\RewardLedgerFixtures;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use LogicException;

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

it('fulfils rewards points only when a matching non-refused award exists', function (): void {
    $start = CarbonImmutable::parse('2026-06-15T12:00:00Z');
    CarbonImmutable::setTestNow($start);
    $owner = $this->newOwner();
    $referrer = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'points-referral@example.com']);
    $recipientType = $referrer->getMorphClass();
    config()->set('rewards.outcomes', ['referral_qualified']);
    config()->set('rewards.recipient_types', [$recipientType]);

    $referralProgram = $this->newProgram($owner, [
        'referrer_reward' => ['driver' => 'reward_points', 'points' => 10],
        'referee_reward' => ['driver' => 'reward_points', 'points' => 20],
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
    $reader->seed(RewardLedgerFixtures::fact(
        $businessKey,
        (string) $referrer->getKey(),
        '2026-06-12T00:00:00Z',
        'referral_qualified',
        ['actorId' => (string) $invitee->getKey()],
    ));
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
        fn () => RewardLedgerFixtures::seedVersionedProgram(
            'referral-points',
            RewardProgramMode::Points,
            ['eligibility' => ['outcomes' => ['referral_qualified']]],
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

    expect($fulfilled->status)->toBe(ReferralRewardStatus::Fulfilled)
        ->and($referrerAward->amount_snapshot['points'])->toBe(10);

    $mismatched = OwnerContext::withOwner($owner, fn () => FulfillReferralReward::run($refereeReward));

    expect($mismatched->status)->toBe(ReferralRewardStatus::Failed)
        ->and($mismatched->metadata['fulfillment_failure'])->toContain('does not match the frozen referral points spec');

    $reversed = OwnerContext::withOwner($owner, fn () => ReverseReferralReward::run($fulfilled, 'host confirmed reversal'));

    expect($reversed->status)->toBe(ReferralRewardStatus::Reversed)
        ->and($reversed->metadata['rewards_reversal'])->toBe('pending_host_corrections_sweep')
        ->and($reversed->metadata['rewards_reversal_business_key'])->toBe($businessKey);
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
