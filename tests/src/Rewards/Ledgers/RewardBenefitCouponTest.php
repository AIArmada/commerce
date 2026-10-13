<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Rewards\Actions\ConsumeRewardBenefitAction;
use AIArmada\Rewards\Actions\CreateRewardBenefitItemAction;
use AIArmada\Rewards\Actions\FulfillRewardBenefitAction;
use AIArmada\Rewards\Actions\RevokeRewardBenefitGrantAction;
use AIArmada\Rewards\Contracts\RewardBenefitCouponFulfillment;
use AIArmada\Rewards\Enums\RewardBenefitGrantStatus;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Models\RewardBenefitGrant;
use AIArmada\Rewards\Tests\Ledgers\Support\FakeRewardBenefitCouponFulfillment;
use AIArmada\Rewards\Tests\Ledgers\Support\RewardLedgerFixtures;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

function couponHarness(string $prefix): array
{
    [$program] = RewardLedgerFixtures::seedVersionedProgram($prefix, RewardProgramMode::Benefit);
    $program = RewardLedgerFixtures::forceActive($program);

    $staff = RewardLedgerFixtures::staffId();
    $recipient = User::factory()->create();

    $item = CreateRewardBenefitItemAction::run(
        $program,
        'coupon-' . $prefix,
        'Coupon benefit',
        ['Member account in good standing'],
        100,
        'pts',
        false,
        50,
        'user',
        $staff,
        'staff',
        null,
        ['voucher' => ['type' => 'fixed', 'value' => 1000, 'currency' => 'MYR']],
    );

    $grant = RewardBenefitGrant::factory()->create([
        'grant_key' => 'grant-' . $prefix . '-' . Str::lower(Str::random(6)),
        'item_id' => $item->getKey(),
        'recipient_type' => 'user',
        'recipient_id' => (string) $recipient->getKey(),
        'status' => RewardBenefitGrantStatus::Reserved,
        'fulfillment_adapter' => 'coupon',
    ]);

    return [$program->refresh(), $item->refresh(), $grant->refresh(), $staff];
}

it('fails closed on coupon benefits without a bound fulfillment', function () {
    [, , $grant, $staff] = couponHarness('coupon-closed');

    expect(fn () => FulfillRewardBenefitAction::run(
        $grant,
        ['handoff' => 'counter'],
        'user',
        $staff,
        'staff',
    ))->toThrow(InvalidArgumentException::class, 'RewardBenefitCouponFulfillment');
});

it('issues, consumes, and revokes coupons through the bound contract', function () {
    $fake = new FakeRewardBenefitCouponFulfillment;
    app()->instance(RewardBenefitCouponFulfillment::class, $fake);

    [, , $grant, $staff] = couponHarness('coupon-open');

    $issued = FulfillRewardBenefitAction::run(
        $grant,
        ['handoff' => 'counter'],
        'user',
        $staff,
        'staff',
    );

    expect($issued->status)->toBe(RewardBenefitGrantStatus::Issued)
        ->and($issued->external_ref)->toStartWith('RB-');

    expect(fn () => ConsumeRewardBenefitAction::run(
        $grant,
        ['coupon_code' => (string) $issued->external_ref],
        'user',
        (string) $grant->recipient_id,
        'member',
    ))->toThrow(InvalidArgumentException::class, 'No canonical coupon usage');

    $fake->recordUsage($grant->refresh());

    $consumed = ConsumeRewardBenefitAction::run(
        $grant,
        ['coupon_code' => (string) $issued->external_ref],
        'user',
        (string) $grant->recipient_id,
        'member',
    );

    expect($consumed->status)->toBe(RewardBenefitGrantStatus::Consumed);

    expect(fn () => RevokeRewardBenefitGrantAction::run(
        $grant,
        'user',
        $staff,
        'staff',
        'changed-mind',
        CarbonImmutable::parse('2026-06-18T00:00:00Z'),
    ))->toThrow(InvalidArgumentException::class);
});

it('revokes unissued-coupon grants through the bound contract', function () {
    $fake = new FakeRewardBenefitCouponFulfillment;
    app()->instance(RewardBenefitCouponFulfillment::class, $fake);

    [, , $grant, $staff] = couponHarness('coupon-revoke');

    FulfillRewardBenefitAction::run($grant, ['handoff' => 'counter'], 'user', $staff, 'staff');

    $revoked = RevokeRewardBenefitGrantAction::run(
        $grant,
        'user',
        $staff,
        'staff',
        'source-error',
        CarbonImmutable::parse('2026-06-18T00:00:00Z'),
    );

    expect($revoked->status)->toBe(RewardBenefitGrantStatus::Revoked);
});
