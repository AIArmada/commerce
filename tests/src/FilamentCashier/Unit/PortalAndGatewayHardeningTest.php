<?php

declare(strict_types=1);

use AIArmada\Cashier\CashierServiceProvider;
use AIArmada\Cashier\Support\UnifiedInvoice;
use AIArmada\CashierChip\Subscription\Subscription as ChipSubscription;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Commerce\Tests\FilamentCashier\Fixtures\ChipBillableUser;
use AIArmada\FilamentCashier\CustomerPortal\Pages\ManageSubscriptions;
use AIArmada\FilamentCashier\CustomerPortal\Pages\ViewInvoices;
use AIArmada\FilamentCashier\Pages\GatewayManagement;
use AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource\Tables\InvoicesTable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

beforeEach(function (): void {
    app()->register(CashierServiceProvider::class);

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

function createPortalUser(string $email): ChipBillableUser
{
    return ChipBillableUser::query()->create([
        'name' => 'Portal User',
        'email' => $email,
        'password' => bcrypt('secret'),
    ]);
}

function invoiceForUserId(string $userId): UnifiedInvoice
{
    $model = new ChipSubscription([
        'billable_id' => $userId,
        'type' => 'default',
    ]);

    return UnifiedInvoice::fromChip($model, $userId);
}

it('clamps subscription load-more increments and totals', function (): void {
    $page = app(ManageSubscriptions::class);
    $page->perGatewayLimit = 190;

    $page->loadMoreSubscriptions(5000);

    expect($page->perGatewayLimit)->toBe(200);

    $page->loadMoreSubscriptions(-10);

    expect($page->perGatewayLimit)->toBe(200);
});

it('clamps invoice limits and slices the merged list', function (): void {
    $user = createPortalUser('invoice-limit@example.com');
    Auth::guard()->setUser($user);

    $page = app(ViewInvoices::class);

    $page->loadMoreInvoices(9999);

    expect($page->limit)->toBe(100);

    $page->limit = 190;
    $page->loadMoreInvoices(9999);

    expect($page->limit)->toBe(200);

    $page->limit = 1;
    $invoices = $page->getInvoices();

    expect($invoices)->toHaveCount(1)
        ->and($page->hasMoreInvoices)->toBeTrue();

    $page->limit = 50;

    expect($page->getInvoices())->toHaveCount(2)
        ->and($page->hasMoreInvoices)->toBeFalse();
});

it('caches healthy gateway probes per owner', function (): void {
    config()->set('chip.collect.brand_id', 'brand-cache');
    config()->set('chip.collect.api_key', 'key-cache');

    $service = Mockery::mock(ChipCollectService::class);
    $service->shouldReceive('getAccountBalance')->once()->andReturn(['balance' => 100]);
    app()->instance(ChipCollectService::class, $service);

    $method = new ReflectionMethod(GatewayManagement::class, 'checkGatewayHealth');
    $page = app(GatewayManagement::class);

    $first = $method->invoke($page, 'chip');
    $second = $method->invoke($page, 'chip');

    expect($first['status'])->toBe('healthy')
        ->and($second)->toBe($first);
});

it('surfaces a generic message when a probe fails', function (): void {
    config()->set('chip.collect.brand_id', 'brand-fail');
    config()->set('chip.collect.api_key', 'key-fail');

    $service = Mockery::mock(ChipCollectService::class);
    $service->shouldReceive('getAccountBalance')->once()->andThrow(new Exception('sk_live_secret_details'));
    app()->instance(ChipCollectService::class, $service);

    $method = new ReflectionMethod(GatewayManagement::class, 'checkGatewayHealth');
    $health = $method->invoke(app(GatewayManagement::class), 'chip');

    expect($health['status'])->toBe('error')
        ->and($health['message'])->toBe(__('filament-cashier::gateway.health.connection_error'))
        ->and($health['message'])->not->toContain('sk_live_secret_details');
});

it('escapes spreadsheet formulas in exported cells', function (): void {
    expect(InvoicesTable::escapeCsvValue('=cmd|/c calc'))->toBe("'=cmd|/c calc")
        ->and(InvoicesTable::escapeCsvValue('+123'))->toBe("'+123")
        ->and(InvoicesTable::escapeCsvValue('@user'))->toBe("'@user")
        ->and(InvoicesTable::escapeCsvValue('INV-0001'))->toBe('INV-0001')
        ->and(InvoicesTable::escapeCsvValue(1299))->toBe('1299');
});

it('restricts invoice access checks to the owning user', function (): void {
    $user = createPortalUser('invoice-owner@example.com');
    $other = createPortalUser('invoice-stranger@example.com');

    Auth::guard()->setUser($user);

    expect(InvoicesTable::isInvoiceAccessible(invoiceForUserId((string) $user->getKey())))->toBeTrue()
        ->and(InvoicesTable::isInvoiceAccessible(invoiceForUserId((string) $other->getKey())))->toBeFalse();

    expect(fn () => InvoicesTable::assertInvoiceAccessible(invoiceForUserId((string) $other->getKey())))
        ->toThrow(AuthorizationException::class);

    Auth::guard()->forgetUser();

    expect(InvoicesTable::isInvoiceAccessible(invoiceForUserId((string) $user->getKey())))->toBeFalse();
});
