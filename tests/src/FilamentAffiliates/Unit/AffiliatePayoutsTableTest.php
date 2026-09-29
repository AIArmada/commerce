<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Affiliates\DisableAffiliate;
use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Actions\Payouts\CreatePayout;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Tables\AffiliatePayoutsTable;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

test('mark completed surfaces a blocked gate as a danger notification instead of throwing', function (): void {
    Gate::before(fn (?object $user): bool => true);

    $affiliate = Affiliate::create([
        'code' => 'TABLE-' . uniqid(),
        'name' => 'Table Payout Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'TABLE-' . uniqid(),
        'value_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);

    ApplyConversionAccounting::run($conversion);

    $payout = CreatePayout::run([$conversion->id]);

    DisableAffiliate::run($affiliate);

    AffiliatePayoutsTable::markCompleted($payout);

    expect($payout->fresh()->status->equals(PendingPayout::class))->toBeTrue();

    $flashed = collect(session('filament.notifications', []));
    $danger = $flashed->firstWhere('status', 'danger');

    expect($danger['title'] ?? null)->toBe('Payout cannot be completed')
        ->and($danger['body'] ?? '')->toContain('can no longer receive payouts');

    Notification::assertNotified('Payout cannot be completed');
});

test('mark completed still completes an eligible payout', function (): void {
    Gate::before(fn (?object $user): bool => true);

    $affiliate = Affiliate::create([
        'code' => 'TABLE-' . uniqid(),
        'name' => 'Table Eligible Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'TABLE-' . uniqid(),
        'value_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);

    ApplyConversionAccounting::run($conversion);

    $payout = CreatePayout::run([$conversion->id]);

    AffiliatePayoutsTable::markCompleted($payout);

    expect($payout->fresh()->status->equals(CompletedPayout::class))->toBeTrue();
});
