<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Affiliates\DisableAffiliate;
use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Actions\Payouts\CreatePayout;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\CancelledPayout;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\FailedPayout;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\ProcessingPayout;
use AIArmada\Authz\Models\Permission;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Pages\ListAffiliatePayouts;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Tables\AffiliatePayoutsTable;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;

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

test('payout row actions are visible only when the current state allows the transition', function (): void {
    config(['affiliates.owner.enabled' => false]);
    Permission::firstOrCreate(['name' => 'affiliates.payout.update', 'guard_name' => 'web']);

    $user = User::create([
        'name' => 'Payout Table Operator',
        'email' => 'payout-table-' . Str::uuid() . '@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->givePermissionTo('affiliates.payout.update');
    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'TABLE-' . Str::uuid(),
        'name' => 'Table Payout Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $createPayout = static fn (string $status): AffiliatePayout => AffiliatePayout::create([
        'reference' => 'TABLE-' . Str::uuid(),
        'total_minor' => 5000,
        'conversion_count' => 1,
        'currency' => 'USD',
        'status' => $status,
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ]);

    $pending = $createPayout(PendingPayout::class);
    $processing = $createPayout(ProcessingPayout::class);
    $completed = $createPayout(CompletedPayout::class);
    $failed = $createPayout(FailedPayout::class);
    $cancelled = $createPayout(CancelledPayout::class);

    Livewire::test(ListAffiliatePayouts::class)
        ->assertTableActionVisible('mark_paid', $pending)
        ->assertTableActionVisible('queue', $pending)
        ->assertTableActionVisible('fail', $pending)
        ->assertTableActionVisible('mark_paid', $processing)
        ->assertTableActionHidden('queue', $processing)
        ->assertTableActionVisible('fail', $processing)
        ->assertTableActionHidden('mark_paid', $completed)
        ->assertTableActionHidden('queue', $completed)
        ->assertTableActionHidden('fail', $completed)
        ->assertTableActionHidden('mark_paid', $failed)
        ->assertTableActionHidden('queue', $failed)
        ->assertTableActionHidden('fail', $failed)
        ->assertTableActionHidden('mark_paid', $cancelled)
        ->assertTableActionHidden('queue', $cancelled)
        ->assertTableActionHidden('fail', $cancelled);
});
