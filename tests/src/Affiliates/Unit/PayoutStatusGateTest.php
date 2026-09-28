<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Affiliates\DisableAffiliate;
use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Actions\Payouts\CreatePayout;
use AIArmada\Affiliates\Actions\Payouts\UpdatePayoutStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\Pending;

function createGateTestAffiliate(string $status = Active::class): Affiliate
{
    return Affiliate::create([
        'code' => 'GATE-' . uniqid(),
        'name' => 'Payout Gate Affiliate',
        'status' => $status,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
}

function recordGateApprovedConversion(Affiliate $affiliate, int $commissionMinor = 6000): AffiliateConversion
{
    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'GATE-' . uniqid(),
        'value_minor' => $commissionMinor * 10,
        'commission_minor' => $commissionMinor,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);

    ApplyConversionAccounting::run($conversion);

    return $conversion;
}

test('CreatePayout blocks affiliates that were never approved', function (): void {
    $affiliate = createGateTestAffiliate(Pending::class);
    $conversion = recordGateApprovedConversion($affiliate);

    expect(fn (): object => CreatePayout::run([$conversion->id]))
        ->toThrow(InvalidArgumentException::class, 'cannot receive payouts');
});

test('CreatePayout blocks disabled affiliates without an override reason', function (): void {
    $affiliate = createGateTestAffiliate();
    $conversion = recordGateApprovedConversion($affiliate);

    DisableAffiliate::run($affiliate);

    expect($affiliate->fresh()->status->equals(Disabled::class))->toBeTrue();

    expect(fn (): object => CreatePayout::run([$conversion->id]))
        ->toThrow(InvalidArgumentException::class, 'payout_override_reason');
});

test('CreatePayout releases earned balances for disabled affiliates with an audited override', function (): void {
    $affiliate = createGateTestAffiliate();
    $conversion = recordGateApprovedConversion($affiliate);

    DisableAffiliate::run($affiliate);

    $payout = CreatePayout::run([$conversion->id], [
        'payout_override_reason' => 'Account closed in good standing; releasing earned balance.',
    ]);

    expect($payout->total_minor)->toBe(6000)
        ->and($payout->metadata['payout_override']['reason'])->toBe('Account closed in good standing; releasing earned balance.')
        ->and($payout->metadata['payout_override']['affiliate_status'])->toBe('disabled');
});

test('CreatePayout ignores override reasons for pending affiliates', function (): void {
    $affiliate = createGateTestAffiliate(Pending::class);
    $conversion = recordGateApprovedConversion($affiliate);

    expect(fn (): object => CreatePayout::run([$conversion->id], [
        'payout_override_reason' => 'Trying to bypass review.',
    ]))->toThrow(InvalidArgumentException::class, 'cannot receive payouts');
});

test('completing a payout is blocked when the affiliate is disabled after creation', function (): void {
    $affiliate = createGateTestAffiliate();
    $conversion = recordGateApprovedConversion($affiliate);

    $payout = CreatePayout::run([$conversion->id]);

    DisableAffiliate::run($affiliate);

    expect(fn (): object => UpdatePayoutStatus::run($payout, 'completed'))
        ->toThrow(InvalidArgumentException::class, 'no longer receive payouts');

    expect($payout->fresh()->paid_at)->toBeNull();
});

test('completing a payout succeeds for a payable affiliate', function (): void {
    $affiliate = createGateTestAffiliate();
    $conversion = recordGateApprovedConversion($affiliate);

    $payout = CreatePayout::run([$conversion->id]);

    $completed = UpdatePayoutStatus::run($payout, 'completed');

    expect($completed->paid_at)->not->toBeNull();
});
