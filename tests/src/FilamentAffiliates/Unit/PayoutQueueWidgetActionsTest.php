<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\ProcessingPayout;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Models\Permission;
use AIArmada\FilamentAffiliates\Widgets\PayoutQueueWidget;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    AffiliatePayout::query()->delete();
    AffiliatePayoutMethod::query()->delete();
    Affiliate::query()->delete();
});

it('processes a payout via the queue widget action', function (): void {
    $user = User::create([
        'name' => 'Queue Widget Processor',
        'email' => 'queue-widget-processor@example.com',
        'password' => 'secret',
    ]);

    Permission::create(['name' => 'affiliates.payout.update', 'guard_name' => 'web']);

    $user->givePermissionTo('affiliates.payout.update');
    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Widget Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->getKey(),
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'Test Bank', 'account_number' => '12345678'],
        'verified_at' => now(),
        'is_default' => true,
    ]);

    $payout = AffiliatePayout::create([
        'reference' => 'PAY-' . Str::uuid(),
        'status' => PendingPayout::class,
        'total_minor' => 5000,
        'currency' => 'USD',
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ]);

    $widget = new PayoutQueueWidget;
    $table = $widget->table(Table::make($widget));

    $process = $table->getAction('process');
    expect($process)->not->toBeNull();

    $process?->call(['record' => $payout]);

    $payout->refresh();

    expect($payout->status)->toBeInstanceOf(ProcessingPayout::class)
        ->and($payout->paid_at)->toBeNull()
        ->and($payout->external_reference)->not->toBeNull();

    expect($payout->events()->count())->toBeGreaterThanOrEqual(1);
});

it('blocks queue widget payout action when user lacks payout update permission', function (): void {
    $user = User::create([
        'name' => 'Queue Widget Unauthorized Processor',
        'email' => 'queue-widget-unauthorized-processor@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Unauthorized Widget Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->getKey(),
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'Test Bank', 'account_number' => '12345678'],
        'verified_at' => now(),
        'is_default' => true,
    ]);

    $payout = AffiliatePayout::create([
        'reference' => 'PAY-' . Str::uuid(),
        'status' => PendingPayout::class,
        'total_minor' => 5000,
        'currency' => 'USD',
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ]);

    $widget = new PayoutQueueWidget;
    $table = $widget->table(Table::make($widget));

    $process = $table->getAction('process');
    expect($process)->not->toBeNull();

    expect(fn () => $process?->call(['record' => $payout]))
        ->toThrow(AuthorizationException::class);

    $payout->refresh();

    expect($payout->status)->toBeInstanceOf(PendingPayout::class);
});
