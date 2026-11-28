<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Rewards\Contracts\RewardSourceFactReader;
use AIArmada\Rewards\Data\RewardBeneficiaryRole;
use AIArmada\Rewards\Data\RewardSourceFact;
use AIArmada\Rewards\Data\RewardVerificationLevel;
use AIArmada\Rewards\Drivers\CompositeRewardSourceFactReader;
use AIArmada\Rewards\Drivers\VouchersRewardBenefitCouponFulfillment;
use AIArmada\Rewards\Enums\RewardBenefitGrantStatus;
use AIArmada\Rewards\Enums\RewardBenefitItemStatus;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Models\RewardBenefitGrant;
use AIArmada\Rewards\Models\RewardBenefitItem;
use AIArmada\Rewards\Models\RewardProgram;
use AIArmada\Rewards\Models\RewardProgramVersion;
use AIArmada\Rewards\Queries\RewardCouponMapper;
use AIArmada\Rewards\Support\RewardMaterialTerms;
use AIArmada\Rewards\Support\RewardVersionResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

test('reward version resolver resolves effective version for a program', function (): void {
    $program = RewardProgram::query()->create([
        'family_key' => 'prog-' . Str::random(6),
        'title' => 'Effective Version Program',
        'purpose' => 'Testing version resolution',
        'mode' => RewardProgramMode::Points,
        'status' => 'active',
    ]);

    $v1Effective = CarbonImmutable::parse('2026-01-01 00:00:00', 'UTC');
    $v2Effective = CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC');

    $v1 = RewardProgramVersion::query()->create([
        'program_id' => $program->getKey(),
        'version' => 1,
        'effective_from' => $v1Effective,
        'rules' => [],
        'terms' => [],
        'material_terms_hash' => RewardMaterialTerms::hash([], []),
        'compatible_with' => [],
        'published_at' => $v1Effective,
    ]);

    $v2 = RewardProgramVersion::query()->create([
        'program_id' => $program->getKey(),
        'version' => 2,
        'effective_from' => $v2Effective,
        'rules' => [],
        'terms' => [],
        'material_terms_hash' => RewardMaterialTerms::hash([], []),
        'compatible_with' => [],
        'published_at' => $v2Effective,
    ]);

    expect(RewardVersionResolver::resolveCurrentForProgram($program, CarbonImmutable::parse('2026-03-01 00:00:00', 'UTC'))?->getKey())
        ->toBe($v1->getKey())
        ->and(RewardVersionResolver::resolveCurrentForProgram($program, CarbonImmutable::parse('2026-07-01 00:00:00', 'UTC'))?->getKey())
        ->toBe($v2->getKey());
});

test('composite reward source fact reader dispatches by key prefix', function (): void {
    $fact = new RewardSourceFact(
        sourceBusinessKey: 'activity:123',
        outcome: 'attendance',
        category: 'general',
        occurredAt: CarbonImmutable::now('UTC'),
        verification: RewardVerificationLevel::StaffVerified,
        isFirstTime: true,
        actorId: (string) Str::uuid(),
        sharerId: null,
        actorRole: RewardBeneficiaryRole::Recipient,
        isClaimedIdentity: true,
        isAttributed: true,
    );

    $reader = new CompositeRewardSourceFactReader([
        'activity:' => new class($fact) implements RewardSourceFactReader
        {
            public function __construct(private readonly RewardSourceFact $fact) {}

            public function read(string $sourceBusinessKey): ?RewardSourceFact
            {
                return $this->fact;
            }
        },
    ]);

    expect($reader->read('activity:123'))->toBe($fact)
        ->and($reader->read('unknown:123'))->toBeNull();
});

test('vouchers reward benefit coupon fulfillment fails closed when voucher terms are missing', function (): void {
    $program = RewardProgram::query()->create([
        'family_key' => 'prog-' . Str::random(6),
        'title' => 'Coupon Program',
        'purpose' => 'Testing coupon fulfillment',
        'mode' => RewardProgramMode::Points,
        'status' => 'active',
    ]);

    $item = RewardBenefitItem::query()->create([
        'program_id' => $program->getKey(),
        'benefit_key' => 'voucher-missing',
        'title' => 'Test Coupon',
        'status' => RewardBenefitItemStatus::Active,
        'conditions' => [],
        'cost_points' => 100,
        'cost_unit' => 'points',
        'direct_entitlement' => false,
        'capacity' => 10,
        'fulfillment_config' => [],
    ]);

    $user = User::factory()->create();
    $grant = RewardBenefitGrant::query()->create([
        'grant_key' => 'grant-' . Str::random(8),
        'item_id' => $item->getKey(),
        'recipient_type' => $user->getMorphClass(),
        'recipient_id' => $user->getKey(),
        'quantity' => 1,
        'status' => RewardBenefitGrantStatus::Reserved,
        'fulfillment_adapter' => 'voucher',
        'actor_type' => 'system',
        'actor_role' => 'system',
        'reserved_at' => CarbonImmutable::now('UTC'),
    ]);

    expect(fn () => (new VouchersRewardBenefitCouponFulfillment)->issue(RewardCouponMapper::grant($grant), RewardCouponMapper::terms($item), CarbonImmutable::now('UTC')))
        ->toThrow(InvalidArgumentException::class);
});
