<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Actions\Conversions\ReverseAffiliateConversion;
use AIArmada\Affiliates\Events\HoldingShortfallDetected;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\RejectedConversion;
use AIArmada\Affiliates\States\ReversedConversion;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Tables\AffiliateConversionsTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    // Gate treats zero-arg callbacks as guest-disallowed; the nullable
    // $user parameter is what lets this before-hook run unauthenticated.
    Gate::before(fn (?object $user): bool => true);
});

function createShortfallHeldConversion(string $code, int $commissionMinor = 5000): AffiliateConversion
{
    $affiliate = Affiliate::create([
        'code' => $code,
        'name' => "Shortfall Test {$code}",
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

function drainShortfallHolding(AffiliateBalance $balance, int $holdingMinor): void
{
    // Legacy divergence: the shared pool holds less than the sum of
    // recorded per-conversion holds (pre-held_minor over-release).
    // Lifetime is untouched — creation already recognized the money.
    DB::table($balance->getTable())->where('id', $balance->getKey())->update([
        'holding_minor' => $holdingMinor,
    ]);
}

test('approving a conversion with a divergent hold credits the full commission and reports the shortfall', function (): void {
    Event::fake([HoldingShortfallDetected::class]);
    Log::spy();

    $conversion = createShortfallHeldConversion('SHORT001');
    $balance = $conversion->affiliate->balanceFor('USD');
    drainShortfallHolding($balance, 0);

    expect(AffiliateConversionsTable::updateStatus($conversion, ApprovedConversion::class))->toBeTrue();

    $balance = $conversion->affiliate->balanceFor('USD')->fresh();

    expect($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(5000)
        ->and($balance->lifetime_earnings_minor)->toBe(5000)
        ->and($conversion->fresh()->held_minor)->toBe(0);

    Log::shouldHaveReceived('warning')->once();

    Event::assertDispatched(HoldingShortfallDetected::class, fn (HoldingShortfallDetected $event): bool => $event->conversion->is($conversion)
        && $event->operation === 'release'
        && $event->requestedMinor === 5000
        && $event->appliedMinor === 0);
});

test('a partially drained pool still pays every approval in full', function (): void {
    Event::fake([HoldingShortfallDetected::class]);

    $first = createShortfallHeldConversion('SHORT002');
    $affiliate = $first->affiliate;

    $second = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'subtotal_minor' => 50000,
        'value_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
        'occurred_at' => now()->subDay(),
    ]);
    app(ApplyConversionAccounting::class)->handle($second);

    drainShortfallHolding($affiliate->balanceFor('USD'), 5000);

    expect(AffiliateConversionsTable::updateStatus($first, ApprovedConversion::class))->toBeTrue();
    expect(AffiliateConversionsTable::updateStatus($second->fresh(), ApprovedConversion::class))->toBeTrue();

    $balance = $affiliate->balanceFor('USD')->fresh();

    expect($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(10000)
        ->and($balance->lifetime_earnings_minor)->toBe(10000);

    Event::assertDispatched(HoldingShortfallDetected::class, 1);
});

test('approving with an upward adjustment credits the lifetime catch-up', function (): void {
    Event::fake([HoldingShortfallDetected::class]);

    $conversion = createShortfallHeldConversion('SHORT003', 5000);
    $conversion->forceFill(['commission_minor' => 7000])->save();

    expect(AffiliateConversionsTable::updateStatus($conversion->fresh(), ApprovedConversion::class))->toBeTrue();

    $balance = $conversion->affiliate->balanceFor('USD')->fresh();

    expect($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(7000)
        ->and($balance->lifetime_earnings_minor)->toBe(7000);

    Event::assertNotDispatched(HoldingShortfallDetected::class);
});

test('rejecting a conversion with a divergent hold voids without throwing and reports the shortfall', function (): void {
    Event::fake([HoldingShortfallDetected::class]);
    Log::spy();

    $conversion = createShortfallHeldConversion('SHORT004');
    drainShortfallHolding($conversion->affiliate->balanceFor('USD'), 0);

    expect(AffiliateConversionsTable::updateStatus($conversion, RejectedConversion::class))->toBeTrue();

    $balance = $conversion->affiliate->balanceFor('USD')->fresh();

    expect($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(0);

    Log::shouldHaveReceived('warning')->once();

    Event::assertDispatched(HoldingShortfallDetected::class, fn (HoldingShortfallDetected $event): bool => $event->conversion->is($conversion)
        && $event->operation === 'void'
        && $event->requestedMinor === 5000
        && $event->appliedMinor === 0);
});

test('reversing a held conversion zeroed after recording releases its hold', function (): void {
    $conversion = createShortfallHeldConversion('SHORT006');
    $affiliate = $conversion->affiliate;

    // Downward adjustment to zero after the hold was recorded.
    $conversion->forceFill(['commission_minor' => 0])->save();

    ReverseAffiliateConversion::run($conversion->fresh(), 'chargeback');

    $balance = $affiliate->balanceFor('USD')->fresh();

    expect($conversion->fresh()->status->equals(ReversedConversion::class))->toBeTrue()
        ->and($conversion->fresh()->held_minor)->toBe(0)
        ->and($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(0);
});

test('approving an off-record conversion reports no shortfall', function (): void {
    Event::fake([HoldingShortfallDetected::class]);

    $affiliate = Affiliate::create([
        'code' => 'SHORT005',
        'name' => 'Off-record shortfall test',
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
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
        'occurred_at' => now()->subDay(),
    ]);

    expect(AffiliateConversionsTable::updateStatus($conversion, ApprovedConversion::class))->toBeTrue();

    $balance = $affiliate->balanceFor('USD')->fresh();

    expect($balance->available_minor)->toBe(5000)
        ->and($balance->lifetime_earnings_minor)->toBe(5000);

    Event::assertNotDispatched(HoldingShortfallDetected::class);
});
