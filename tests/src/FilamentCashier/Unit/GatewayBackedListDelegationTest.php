<?php

declare(strict_types=1);

use AIArmada\Cashier\CashierServiceProvider;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Commerce\Tests\FilamentCashier\Fixtures\ChipBillableUser;
use AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource\Pages\ListInvoices;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Pages\ListSubscriptions;
use Illuminate\Support\Facades\Auth;

beforeEach(function (): void {
    app()->register(CashierServiceProvider::class);

    config()->set('cashier.models.billable', ChipBillableUser::class);
    config()->set('cashier.gateways', [
        'chip' => [
            'driver' => 'chip',
            'label' => 'CHIP',
            'icon' => 'heroicon-o-cube',
            'color' => 'emerald',
            'dashboard_url' => 'https://gate.chip-in.asia',
        ],
    ]);
});

it('delegates unified subscription listing to the configured gateway client', function (): void {
    $user = ChipBillableUser::query()->create([
        'name' => 'Subscription List User',
        'email' => 'subscription-list@example.com',
        'password' => bcrypt('secret'),
    ]);

    Auth::guard()->setUser($user);

    $gateway = Mockery::mock(GatewayContract::class);
    $gateway->shouldReceive('subscriptions')->once()->with($user)->andReturn(collect());
    Cashier::shouldReceive('supportedGateways')->once()->andReturn(['chip']);
    Cashier::shouldReceive('gateway')->once()->with('chip')->andReturn($gateway);

    expect(app(ListSubscriptions::class)->getTableRecords())->toBeEmpty();
});

it('delegates unified invoice listing to the configured gateway client', function (): void {
    $user = ChipBillableUser::query()->create([
        'name' => 'Invoice List User',
        'email' => 'invoice-list@example.com',
        'password' => bcrypt('secret'),
    ]);

    Auth::guard()->setUser($user);

    $gateway = Mockery::mock(GatewayContract::class);
    $gateway->shouldReceive('invoices')->once()->with($user, ['limit' => 100])->andReturn(collect());
    Cashier::shouldReceive('supportedGateways')->once()->andReturn(['chip']);
    Cashier::shouldReceive('gateway')->once()->with('chip')->andReturn($gateway);

    expect(app(ListInvoices::class)->getTableRecords())->toBeEmpty();
});
