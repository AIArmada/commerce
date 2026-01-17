<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Models\Order;

it('records affiliate conversions when order commission attribution is required', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'ORDER-AFF',
        'name' => 'Order Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 100,
        'currency' => 'MYR',
    ]);

    $cart = app('cart')->getCurrentCart();
    app(AffiliateService::class)->attachToCartByCode($affiliate->code, $cart);

    $cartId = $cart->getId();

    expect($cartId)->not()->toBeNull();

    $order = Order::factory()->paid()->create([
        'metadata' => [
            'cart_id' => $cartId,
        ],
    ]);

    event(new CommissionAttributionRequired($order));

    expect(AffiliateConversion::count())->toBe(1);
});
