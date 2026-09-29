<?php

declare(strict_types=1);

use AIArmada\Cart\Snapshots\CartSnapshot as Cart;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\FilamentVouchers\Widgets\AppliedVouchersWidget;
use AIArmada\FilamentVouchers\Widgets\QuickApplyVoucherWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherCartStatsWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherSuggestionsWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherUsageTimelineWidget;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use Livewire\Attributes\Locked;

uses(TestCase::class);

dataset('cartRecordWidgets', [
    AppliedVouchersWidget::class,
    QuickApplyVoucherWidget::class,
    VoucherSuggestionsWidget::class,
]);

dataset('voucherRecordWidgets', [
    VoucherUsageTimelineWidget::class,
    VoucherCartStatsWidget::class,
]);

dataset('allRecordWidgets', [
    AppliedVouchersWidget::class,
    QuickApplyVoucherWidget::class,
    VoucherSuggestionsWidget::class,
    VoucherUsageTimelineWidget::class,
    VoucherCartStatsWidget::class,
]);

beforeEach(function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', false);
    config()->set('vouchers.owner.auto_assign_on_create', true);
    config()->set('cart.owner.enabled', true);
    config()->set('cart.owner.include_global', false);
    config()->set('cart.owner.auto_assign_on_create', true);
});

function recordWidgetOwner(string $name): User
{
    return User::query()->create([
        'name' => $name,
        'email' => Str::slug($name) . '@example.com',
        'password' => 'secret',
    ]);
}

function recordWidgetCart(User $owner): Cart
{
    return OwnerContext::withOwner(
        $owner,
        fn () => Cart::query()->create([
            'instance' => 'default',
            'identifier' => 'cart-' . Str::random(8),
            'currency' => 'USD',
            'subtotal' => 10000,
            'total' => 10000,
        ]),
    );
}

function recordWidgetVoucher(User $owner): Voucher
{
    return OwnerContext::withOwner(
        $owner,
        fn () => Voucher::query()->create([
            'code' => 'RW-' . mb_strtoupper(Str::random(6)),
            'name' => 'Record Widget',
            'type' => VoucherType::Fixed,
            'value' => 500,
            'currency' => 'USD',
            'starts_at' => now()->subDay(),
        ]),
    );
}

it('stamps cart records at mount', function (string $widgetClass): void {
    $owner = recordWidgetOwner('Cart Mount Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $widget = app($widgetClass);
    $widget->record = recordWidgetCart($owner);
    $widget->mountVerifiesRecordOwnerContext();

    expect($widget->record)->not->toBeNull()
        ->and($widget->recordOwnerStamp)->toBeString();
})->with('cartRecordWidgets');

it('stamps voucher records at mount', function (string $widgetClass): void {
    $owner = recordWidgetOwner('Voucher Mount Owner');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $widget = app($widgetClass);
    $widget->record = recordWidgetVoucher($owner);
    $widget->mountVerifiesRecordOwnerContext();

    expect($widget->record)->not->toBeNull()
        ->and($widget->recordOwnerStamp)->toBeString();
})->with('voucherRecordWidgets');

it('clears cart records after an owner switch', function (string $widgetClass): void {
    $ownerA = recordWidgetOwner('Cart Switch A');
    $ownerB = recordWidgetOwner('Cart Switch B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $widget = app($widgetClass);
    $widget->record = recordWidgetCart($ownerA);
    $widget->mountVerifiesRecordOwnerContext();

    expect($widget->record)->not->toBeNull();

    OwnerContext::withOwner($ownerB, function () use ($widget): void {
        $widget->hydrateVerifiesRecordOwnerContext();
    });

    expect($widget->record)->toBeNull()
        ->and($widget->recordOwnerStamp)->toBeNull();
})->with('cartRecordWidgets');

it('clears voucher records after an owner switch', function (string $widgetClass): void {
    $ownerA = recordWidgetOwner('Voucher Switch A');
    $ownerB = recordWidgetOwner('Voucher Switch B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $widget = app($widgetClass);
    $widget->record = recordWidgetVoucher($ownerA);
    $widget->mountVerifiesRecordOwnerContext();

    expect($widget->record)->not->toBeNull();

    OwnerContext::withOwner($ownerB, function () use ($widget): void {
        $widget->hydrateVerifiesRecordOwnerContext();
    });

    expect($widget->record)->toBeNull()
        ->and($widget->recordOwnerStamp)->toBeNull();
})->with('voucherRecordWidgets');

it('exposes the owner guard hooks and locked record props', function (string $widgetClass): void {
    $widget = app($widgetClass);

    expect(method_exists($widget, 'mountVerifiesRecordOwnerContext'))->toBeTrue()
        ->and(method_exists($widget, 'hydrateVerifiesRecordOwnerContext'))->toBeTrue()
        ->and((new ReflectionProperty($widget, 'record'))->getAttributes(Locked::class))->not->toBeEmpty()
        ->and((new ReflectionProperty($widget, 'recordOwnerStamp'))->getAttributes(Locked::class))->not->toBeEmpty();
})->with('allRecordWidgets');

it('renders empty applied vouchers once the cart record is cleared', function (): void {
    $ownerA = recordWidgetOwner('Applied Owner A');
    $ownerB = recordWidgetOwner('Applied Owner B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $widget = app(AppliedVouchersWidget::class);
    $widget->record = recordWidgetCart($ownerA);
    $widget->mountVerifiesRecordOwnerContext();

    OwnerContext::withOwner($ownerB, function () use ($widget): void {
        $widget->hydrateVerifiesRecordOwnerContext();
    });

    $method = new ReflectionMethod($widget, 'getAppliedVouchersQuery');
    $method->setAccessible(true);

    expect($widget->record)->toBeNull()
        ->and($method->invoke($widget))->toBeEmpty();
});

it('renders no suggestions once the cart record is cleared', function (): void {
    $ownerA = recordWidgetOwner('Suggestions Owner A');
    $ownerB = recordWidgetOwner('Suggestions Owner B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $widget = app(VoucherSuggestionsWidget::class);
    $widget->record = recordWidgetCart($ownerA);
    $widget->mountVerifiesRecordOwnerContext();

    OwnerContext::withOwner($ownerB, function () use ($widget): void {
        $widget->hydrateVerifiesRecordOwnerContext();
    });

    expect($widget->record)->toBeNull()
        ->and($widget->getEligibleVouchers())->toBeEmpty();
});

it('renders empty usage stats once the voucher record is cleared', function (): void {
    $ownerA = recordWidgetOwner('Usage Owner A');
    $ownerB = recordWidgetOwner('Usage Owner B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $voucher = recordWidgetVoucher($ownerA);

    VoucherUsage::query()->create([
        'voucher_id' => $voucher->id,
        'discount_amount' => 500,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'used_at' => now()->subMinutes(5),
    ]);

    $widget = app(VoucherUsageTimelineWidget::class);
    $widget->record = $voucher;
    $widget->mountVerifiesRecordOwnerContext();

    expect($widget->getSummaryStats()['total_redemptions'])->toBe(1);

    OwnerContext::withOwner($ownerB, function () use ($widget): void {
        $widget->hydrateVerifiesRecordOwnerContext();
    });

    expect($widget->record)->toBeNull()
        ->and($widget->getTimelineEvents())->toBeEmpty()
        ->and($widget->getSummaryStats()['total_redemptions'])->toBe(0);
});

it('renders no cart stats once the voucher record is cleared', function (): void {
    $ownerA = recordWidgetOwner('Stats Owner A');
    $ownerB = recordWidgetOwner('Stats Owner B');
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $widget = app(VoucherCartStatsWidget::class);
    $widget->record = recordWidgetVoucher($ownerA);
    $widget->mountVerifiesRecordOwnerContext();

    $method = new ReflectionMethod($widget, 'getStats');
    $method->setAccessible(true);

    expect($method->invoke($widget))->not->toBeEmpty();

    OwnerContext::withOwner($ownerB, function () use ($widget): void {
        $widget->hydrateVerifiesRecordOwnerContext();
    });

    expect($widget->record)->toBeNull()
        ->and($method->invoke($widget))->toBeEmpty();
});
