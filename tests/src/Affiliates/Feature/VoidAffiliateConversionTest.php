<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Actions\Conversions\VoidAffiliateConversion;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\RejectedConversion;
use AIArmada\Affiliates\States\ReversedConversion;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Tables\AffiliateConversionsTable;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::before(fn (?object $user): bool => true);
});

function createVoidableConversion(string $code, int $commissionMinor = 5000): AffiliateConversion
{
    $affiliate = Affiliate::create([
        'code' => $code,
        'name' => "Void Test {$code}",
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

test('voiding a held conversion rejects it and voids holding', function (): void {
    $conversion = createVoidableConversion('VOID001');

    $result = VoidAffiliateConversion::run($conversion, 'fraud confirmed');

    expect($result->status->equals(RejectedConversion::class))->toBeTrue();

    $balance = $conversion->affiliate->balanceFor('USD')->fresh();

    expect($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(0);
});

test('voiding an approved conversion rejects it and voids available', function (): void {
    $conversion = createVoidableConversion('VOID002');

    expect(AffiliateConversionsTable::updateStatus($conversion, ApprovedConversion::class))->toBeTrue();

    VoidAffiliateConversion::run($conversion->fresh(), 'fraud confirmed');

    $balance = $conversion->affiliate->balanceFor('USD')->fresh();

    expect($conversion->fresh()->status->equals(RejectedConversion::class))->toBeTrue()
        ->and($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(0);
});

test('voiding a paid conversion reverses it with a clawback leg', function (): void {
    $conversion = createVoidableConversion('VOID003');

    expect(AffiliateConversionsTable::updateStatus($conversion, ApprovedConversion::class))->toBeTrue();

    $conversion = $conversion->fresh();
    $previousStatus = $conversion->status;
    $conversion->update(['status' => PaidConversion::class]);
    app(ApplyConversionAccounting::class)->handle($conversion->fresh(), $previousStatus);

    $result = VoidAffiliateConversion::run($conversion->fresh(), 'fraud confirmed');

    expect($result->status->equals(ReversedConversion::class))->toBeTrue()
        ->and($result->conversion_type)->toBe('reversal')
        ->and($result->commission_minor)->toBe(-5000);

    $balance = $conversion->affiliate->balanceFor('USD')->fresh();

    expect($conversion->fresh()->status->equals(ReversedConversion::class))->toBeTrue()
        ->and($balance->available_minor)->toBe(-5000)
        ->and($balance->lifetime_earnings_minor)->toBe(0);
});

test('voiding a terminal conversion is a no-op', function (): void {
    $conversion = createVoidableConversion('VOID004');

    VoidAffiliateConversion::run($conversion, 'first');
    $afterFirst = $conversion->affiliate->balanceFor('USD')->fresh()->toArray();

    $result = VoidAffiliateConversion::run($conversion->fresh(), 'second');

    expect($result->status->equals(RejectedConversion::class))->toBeTrue();

    $afterSecond = $conversion->affiliate->balanceFor('USD')->fresh()->toArray();

    expect($afterSecond['holding_minor'])->toBe($afterFirst['holding_minor'])
        ->and($afterSecond['available_minor'])->toBe($afterFirst['available_minor'])
        ->and($afterSecond['lifetime_earnings_minor'])->toBe($afterFirst['lifetime_earnings_minor']);
});
