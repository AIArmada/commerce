<?php

declare(strict_types=1);

use AIArmada\Cart\Cart as LiveCart;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot as Cart;
use AIArmada\Cart\Runtime\CartInstanceManager;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Widgets\VoucherSuggestionsWidget;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Runtime\CartVoucherApplicator;
use AIArmada\Vouchers\Runtime\VoucherSnapshot;

uses(TestCase::class);

function suggestionCart(): Cart
{
    config()->set('vouchers.owner.enabled', false);

    return Cart::query()->create([
        'instance' => 'default',
        'identifier' => 'suggest-' . mb_strtolower(Str::random(6)),
        'currency' => 'USD',
        'subtotal' => 10000,
        'total' => 10000,
    ]);
}

function suggestionVoucher(array $overrides = []): Voucher
{
    return Voucher::query()->create(array_merge([
        'code' => 'SUG-' . mb_strtoupper(Str::random(6)),
        'name' => 'Suggestion',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'USD',
        'status' => 'active',
        'starts_at' => now()->subDay(),
    ], $overrides));
}

it('applies suggestions by record key, resolving the code server-side', function (): void {
    $cart = suggestionCart();
    $voucher = suggestionVoucher();

    $live = new LiveCart(new InMemoryStorage, $cart->identifier);
    $manager = Mockery::mock(CartInstanceManager::class);
    $manager->shouldReceive('resolve')->with($cart->instance, $cart->identifier)->andReturn($live);
    app()->instance(CartInstanceManager::class, $manager);

    $applicator = Mockery::mock(CartVoucherApplicator::class);
    $applicator->shouldReceive('handle')->once()->with($live, $voucher->code)
        ->andReturn(new VoucherCondition(VoucherSnapshot::fromModel($voucher)));
    app()->instance(CartVoucherApplicator::class, $applicator);

    $widget = app(VoucherSuggestionsWidget::class);
    $widget->record = $cart;
    $widget->applySuggestion((string) $voucher->getKey());

    // A hostile code string can no longer ride the action argument: unknown
    // keys resolve to nothing and apply nothing.
    $widget->applySuggestion("x'); DROP TABLE vouchers; --");

    // The typed applicator expectation also verifies that the hostile key caused no write.
    expect($widget->record->getKey())->toBe($cart->getKey());
});

it('resolves the cart once no matter how many candidates exist', function (): void {
    $cart = suggestionCart();
    suggestionVoucher();
    suggestionVoucher();
    suggestionVoucher();

    $live = new LiveCart(new InMemoryStorage, $cart->identifier);
    $manager = Mockery::mock(CartInstanceManager::class);
    $manager->shouldReceive('resolve')->once()->with($cart->instance, $cart->identifier)->andReturn($live);
    app()->instance(CartInstanceManager::class, $manager);

    $widget = app(VoucherSuggestionsWidget::class);
    $widget->record = $cart;

    expect($widget->getEligibleVouchers())->not->toBeEmpty();
});
