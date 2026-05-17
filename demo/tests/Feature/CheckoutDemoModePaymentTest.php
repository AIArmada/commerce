<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Completed;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('checkout falls back to demo payment simulation when CHIP is not configured', function (): void {
    config()->set('chip.collect.api_key', null);
    config()->set('chip.collect.brand_id', null);

    /** @var User $owner */
    $owner = User::factory()->create();

    $product = OwnerContext::withOwner($owner, function (): Product {
        return Product::create([
            'name' => 'Demo Product',
            'sku' => 'DEMO-001',
            'price' => 10_00,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);
    });

    OwnerContext::withOwner($owner, function () use ($product): void {
        PriceList::create([
            'name' => 'Retail',
            'slug' => 'retail',
            'currency' => 'MYR',
            'is_default' => true,
            'is_active' => true,
        ]);

        $priceList = PriceList::query()->firstOrFail();

        Price::create([
            'price_list_id' => $priceList->id,
            'priceable_type' => $product->getMorphClass(),
            'priceable_id' => $product->getKey(),
            'amount' => 10_00,
            'currency' => 'MYR',
        ]);
    });

    /** @var TestCase $this */
    $this->actingAs($owner);

    $this->post(route('shop.cart.add'), [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertRedirect();

    $response = $this->post(route('shop.checkout.process'), [
        'email' => 'demo-mode@example.com',
        'phone' => '+60123456789',
        'first_name' => 'Demo',
        'last_name' => 'Mode',
        'line1' => '1 Jalan Demo',
        'line2' => null,
        'city' => 'Kuala Lumpur',
        'state' => 'WP Kuala Lumpur',
        'postcode' => '50000',
        'shipping_method' => 'jnt_standard',
        'payment_method' => 'fpx',
    ]);

    $checkoutSession = CheckoutSession::withoutOwnerScope()
        ->latest('created_at')
        ->firstOrFail();

    expect($checkoutSession)->not()->toBeNull()
        ->and($checkoutSession?->selected_payment_gateway)->toBe('demo')
        ->and($checkoutSession?->payment_redirect_url)->not()->toBeNull()
        ->and($checkoutSession?->status instanceof AwaitingPayment)->toBeTrue();

    $response->assertRedirect(route('demo.payment.show', ['checkoutSession' => $checkoutSession]));

    $orderBeforeCallback = OwnerContext::withOwner($owner, fn () => Order::query()->latest('created_at')->first());

    expect($orderBeforeCallback)->toBeNull();

    $callbackResponse = $this->post(route('demo.payment.process', [
        'checkoutSession' => $checkoutSession,
        'decision' => 'success',
    ]));

    $callbackUrl = $callbackResponse->headers->get('Location');

    expect(is_string($callbackUrl) && $callbackUrl !== '')->toBeTrue();

    if (! is_string($callbackUrl) || $callbackUrl === '') {
        return;
    }

    $finalResponse = $this->get($callbackUrl);

    $checkoutSession->refresh();

    expect($checkoutSession->status instanceof Completed)->toBeTrue()
        ->and($checkoutSession->order_id)->not()->toBeNull()
        ->and($checkoutSession->payment_data['demo_gateway']['status'] ?? null)->toBe('completed');

    $orderId = $checkoutSession->order_id;

    expect(is_string($orderId) && $orderId !== '')->toBeTrue();

    if (! is_string($orderId) || $orderId === '') {
        return;
    }

    $order = OwnerContext::withOwner($owner, fn () => Order::query()->find($orderId));

    expect($order)->not()->toBeNull();

    $finalResponse->assertRedirect(route('shop.order.success', ['order' => $orderId]));
});
