<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Conversions\Actions\ApplyConversionAccounting;
use AIArmada\Affiliates\Features\Conversions\Actions\ReverseAffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\ReversedConversion;
use AIArmada\FilamentAffiliates\Features\Conversions\EntryPoints\Filament\Resources\AffiliateConversionResource\Tables\AffiliateConversionsTable;
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

    $reversal = app(ReverseAffiliateConversion::class)->handle($conversion, 'chargeback');

    expect($conversion->fresh()->status->equals(ReversedConversion::class))->toBeTrue()
        ->and($reversal->commission_minor)->toBe(-5000)
        ->and($reversal->status->equals(ReversedConversion::class))->toBeTrue();

    $balance = AffiliateBalance::query()->where('affiliate_id', $conversion->affiliate->getKey())->where('currency', 'USD')->first()->fresh();

    expect($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(0);
});

test('reversing an approved conversion voids available and lifetime', function (): void {
    $conversion = createReversibleConversion('REV002');

    expect(AffiliateConversionsTable::updateStatus($conversion, ApprovedConversion::class))->toBeTrue();

    app(ReverseAffiliateConversion::class)->handle($conversion->fresh(), 'refund');

    $balance = AffiliateBalance::query()->where('affiliate_id', $conversion->affiliate->getKey())->where('currency', 'USD')->first()->fresh();

    expect($conversion->fresh()->status->equals(ReversedConversion::class))->toBeTrue()
        ->and($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(0);
});

test('reversing a paid conversion claws back below zero and cuts lifetime', function (): void {
    $conversion = createReversibleConversion('REV003');

    expect(AffiliateConversionsTable::updateStatus($conversion, ApprovedConversion::class))->toBeTrue();

    $conversion = markConversionPaid($conversion->fresh());

    expect(AffiliateBalance::query()->where('affiliate_id', $conversion->affiliate->getKey())->where('currency', 'USD')->first()->fresh()->available_minor)->toBe(0);

    app(ReverseAffiliateConversion::class)->handle($conversion, 'chargeback');

    $balance = AffiliateBalance::query()->where('affiliate_id', $conversion->affiliate->getKey())->where('currency', 'USD')->first()->fresh();

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

    $balance = AffiliateBalance::query()->where('affiliate_id', $conversion->affiliate->getKey())->where('currency', 'USD')->first()->fresh();

    expect($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(0);
});

test('reversing a zero-commission conversion only flips status', function (): void {
    $conversion = createReversibleConversion('REV005', 0);

    app(ReverseAffiliateConversion::class)->handle($conversion, 'void');

    $balance = AffiliateBalance::query()->where('affiliate_id', $conversion->affiliate->getKey())->where('currency', 'USD')->first();

    expect($conversion->fresh()->status->equals(ReversedConversion::class))->toBeTrue()
        ->and($balance?->holding_minor ?? 0)->toBe(0)
        ->and($balance?->available_minor ?? 0)->toBe(0)
        ->and($balance?->lifetime_earnings_minor ?? 0)->toBe(0);
});
