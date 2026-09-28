<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\RejectedConversion;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Tables\AffiliateConversionsTable;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    // Gate treats zero-arg callbacks as guest-disallowed; the nullable
    // $user parameter is what lets this before-hook run unauthenticated.
    Gate::before(fn (?object $user): bool => true);
});

function createHeldConversion(string $code, int $commissionMinor = 5000): AffiliateConversion
{
    $affiliate = Affiliate::create([
        'code' => $code,
        'name' => "Holding Test {$code}",
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

test('approving a conversion releases its commission from holding', function (): void {
    $conversion = createHeldConversion('HOLD001');

    expect($conversion->affiliate->balanceFor('USD')->holding_minor)->toBe(5000);

    expect(AffiliateConversionsTable::updateStatus($conversion, ApprovedConversion::class))->toBeTrue();

    $balance = $conversion->affiliate->balanceFor('USD')->fresh();

    expect($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(5000)
        ->and($conversion->fresh()->status->equals(ApprovedConversion::class))->toBeTrue();
});

test('approving twice credits available only once', function (): void {
    $conversion = createHeldConversion('HOLD003');

    expect(AffiliateConversionsTable::updateStatus($conversion, ApprovedConversion::class))->toBeTrue();
    expect(AffiliateConversionsTable::updateStatus($conversion->fresh(), ApprovedConversion::class))->toBeTrue();

    $balance = $conversion->affiliate->balanceFor('USD')->fresh();

    expect($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(5000);
});

test('rejecting a conversion voids its held commission', function (): void {
    $conversion = createHeldConversion('HOLD002');

    expect(AffiliateConversionsTable::updateStatus($conversion, RejectedConversion::class))->toBeTrue();

    $balance = $conversion->affiliate->balanceFor('USD')->fresh();

    expect($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(0);
});
