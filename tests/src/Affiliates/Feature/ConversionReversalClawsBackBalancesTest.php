<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Actions\Conversions\ReverseAffiliateConversion;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\ReversedConversion;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Tables\AffiliateConversionsTable;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::before(fn (?object $user): bool => true);
});

function createReversibleConversion(string $code, int $commissionMinor = 5000): AffiliateConversion
{
    $affiliate = Affiliate::create([
        'code' => $code,
        'name' => "Reversal Test {$code}",
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'subtotal_minor' => 50000,
        'value_minor' => 50000,
        'commission_minor' => $commissionMinor,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
        'occurred_at' => now()->subDay(),
    ]);

    app(ApplyConversionAccounting::class)->handle($conversion);

    return $conversion;
}

function markConversionPaid(AffiliateConversion $conversion): AffiliateConversion
{
    $previousStatus = $conversion->status;
    $conversion->update(['status' => PaidConversion::class]);
    app(ApplyConversionAccounting::class)->handle($conversion->fresh(), $previousStatus);

    return $conversion->fresh();
}

test('reversing a held conversion voids holding and lifetime', function (): void {
    $conversion = createReversibleConversion('REV001');

    $reversal = ReverseAffiliateConversion::run($conversion, 'chargeback');

    expect($conversion->fresh()->status->equals(ReversedConversion::class))->toBeTrue()
        ->and($reversal->commission_minor)->toBe(-5000)
        ->and($reversal->status->equals(ReversedConversion::class))->toBeTrue();

    $balance = $conversion->affiliate->balanceFor('USD')->fresh();

    expect($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(0);
});

test('reversing an approved conversion voids available and lifetime', function (): void {
    $conversion = createReversibleConversion('REV002');

    expect(AffiliateConversionsTable::updateStatus($conversion, ApprovedConversion::class))->toBeTrue();

    ReverseAffiliateConversion::run($conversion->fresh(), 'refund');

    $balance = $conversion->affiliate->balanceFor('USD')->fresh();

    expect($conversion->fresh()->status->equals(ReversedConversion::class))->toBeTrue()
        ->and($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(0);
});

test('reversing a paid conversion claws back below zero and cuts lifetime', function (): void {
    $conversion = createReversibleConversion('REV003');

    expect(AffiliateConversionsTable::updateStatus($conversion, ApprovedConversion::class))->toBeTrue();

    $conversion = markConversionPaid($conversion->fresh());

    expect($conversion->affiliate->balanceFor('USD')->fresh()->available_minor)->toBe(0);

    ReverseAffiliateConversion::run($conversion, 'chargeback');

    $balance = $conversion->affiliate->balanceFor('USD')->fresh();

    expect($conversion->fresh()->status->equals(ReversedConversion::class))->toBeTrue()
        ->and($balance->available_minor)->toBe(-5000)
        ->and($balance->lifetime_earnings_minor)->toBe(0);
});

test('reversal is idempotent and moves money once', function (): void {
    $conversion = createReversibleConversion('REV004');

    $action = app(ReverseAffiliateConversion::class);

    $first = $action->handle($conversion, 'duplicate');
    $second = $action->handle($conversion->fresh(), 'duplicate');

    expect($second->getKey())->toBe($first->getKey());

    $balance = $conversion->affiliate->balanceFor('USD')->fresh();

    expect($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(0);
});

test('reversing a zero-commission conversion only flips status', function (): void {
    $conversion = createReversibleConversion('REV005', 0);

    ReverseAffiliateConversion::run($conversion, 'void');

    $balance = $conversion->affiliate->balanceFor('USD');

    expect($conversion->fresh()->status->equals(ReversedConversion::class))->toBeTrue()
        ->and($balance?->holding_minor ?? 0)->toBe(0)
        ->and($balance?->available_minor ?? 0)->toBe(0)
        ->and($balance?->lifetime_earnings_minor ?? 0)->toBe(0);
});
