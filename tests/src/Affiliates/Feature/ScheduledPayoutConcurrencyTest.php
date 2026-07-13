<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Payouts\ClaimScheduledPayout;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Models\AffiliatePayoutHold;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PendingPayout;

function createPayoutCandidate(int $availableMinor = 10_000, int $commissionMinor = 10_000): array
{
    $affiliate = Affiliate::query()->create([
        'code' => 'CLAIM-' . uniqid(),
        'name' => 'Atomic Claim Affiliate',
        'contact_email' => uniqid() . '@example.test',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $balance = AffiliateBalance::query()->create([
        'affiliate_id' => $affiliate->id,
        'available_minor' => $availableMinor,
        'minimum_payout_minor' => 5_000,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'ORDER-' . uniqid(),
        'value_minor' => 50_000,
        'commission_minor' => $commissionMinor,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now()->subDays(31),
    ]);

    return [$affiliate, $balance, $conversion];
}

test('repeated workers reserve one payout operation and never overdraw balance', function (): void {
    [$affiliate, $balance, $conversion] = createPayoutCandidate();
    $action = app(ClaimScheduledPayout::class);

    $first = $action->handle((string) $affiliate->id, 5_000);
    $second = $action->handle((string) $affiliate->id, 5_000);

    expect($first)->toBeInstanceOf(AffiliatePayoutOperation::class)
        ->and($second)->toBeNull()
        ->and(AffiliatePayoutOperation::query()->where('affiliate_id', $affiliate->id)->count())->toBe(1)
        ->and(AffiliatePayout::query()->where('payee_id', $affiliate->id)->count())->toBe(1)
        ->and($balance->refresh()->available_minor)->toBe(0)
        ->and($conversion->refresh()->affiliate_payout_id)->not->toBeNull();
});

test('current holds and pending payouts are rechecked before reserving', function (): void {
    [$affiliate, $balance] = createPayoutCandidate();

    AffiliatePayoutHold::query()->create([
        'affiliate_id' => $affiliate->id,
        'reason' => 'Review',
    ]);

    expect(app(ClaimScheduledPayout::class)->handle((string) $affiliate->id, 5_000))->toBeNull()
        ->and($balance->refresh()->available_minor)->toBe(10_000);

    $affiliate->payoutHolds()->update(['released_at' => now()]);
    AffiliatePayout::query()->create([
        'reference' => 'PENDING-' . uniqid(),
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->id,
        'total_minor' => 5_000,
        'currency' => 'USD',
        'status' => PendingPayout::class,
    ]);

    expect(app(ClaimScheduledPayout::class)->handle((string) $affiliate->id, 5_000))->toBeNull()
        ->and($balance->refresh()->available_minor)->toBe(10_000);
});

test('reservation is limited to approved unlinked commission value', function (): void {
    [$affiliate, $balance] = createPayoutCandidate(20_000, 7_500);

    $operation = app(ClaimScheduledPayout::class)->handle((string) $affiliate->id, 5_000);

    expect($operation?->amount_minor)->toBe(7_500)
        ->and($balance->refresh()->available_minor)->toBe(12_500);
});

test('reservation links only conversions fully covered by the available balance', function (): void {
    [$affiliate, $balance, $firstConversion] = createPayoutCandidate(10_000, 7_500);

    $secondConversion = AffiliateConversion::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'ORDER-' . uniqid(),
        'value_minor' => 50_000,
        'commission_minor' => 7_500,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now()->subDays(30),
    ]);

    $operation = app(ClaimScheduledPayout::class)->handle((string) $affiliate->id, 5_000);

    expect($operation?->amount_minor)->toBe(7_500)
        ->and($operation?->payout?->conversion_count)->toBe(1)
        ->and($balance->refresh()->available_minor)->toBe(2_500)
        ->and($firstConversion->refresh()->affiliate_payout_id)->not->toBeNull()
        ->and($secondConversion->refresh()->affiliate_payout_id)->toBeNull();
});

test('dry run does not claim a payout when the oldest conversion cannot be fully funded', function (): void {
    [$affiliate] = createPayoutCandidate(5_000, 6_000);

    expect(app(ClaimScheduledPayout::class)->isEligibleSnapshot((string) $affiliate->id, 5_000))->toBeFalse();
});
