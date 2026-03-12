<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Models\Order;

it('records affiliate conversions when order commission attribution is required', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'ORDER-AFF',
        'name' => 'Order Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 100,
        'currency' => 'MYR',
    ]);

    $cart = app('cart')->getCurrentCart();
    app(AffiliateService::class)->attachToCartByCode($affiliate->code, $cart);

    $cartId = $cart->getId();
    $orderReference = 'ORD-LISTENER-001';

    expect($cartId)->not()->toBeNull();

    $order = Order::factory()->paid()->create([
        'order_number' => $orderReference,
        'metadata' => [
            'cart_id' => $cartId,
        ],
    ]);

    event(new CommissionAttributionRequired($order));

    $conversion = AffiliateConversion::query()->sole();

    expect($conversion->external_reference)->toBe($orderReference)
        ->and($conversion->order_reference)->toBe($orderReference)
        ->and($conversion->conversion_type)->toBe('purchase');
});
