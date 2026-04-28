<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

it('fails closed when an order carries a malformed owner tuple', function (): void {
    Log::spy();

    $affiliate = Affiliate::create([
        'code' => 'ORDER-AFF-MALFORMED',
        'name' => 'Order Affiliate Malformed',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 100,
        'currency' => 'MYR',
    ]);

    $cart = app('cart')->getCurrentCart();
    app(AffiliateService::class)->attachToCartByCode($affiliate->code, $cart);

    $cartId = $cart->getId();

    expect($cartId)->not()->toBeNull();

    $order = Order::factory()->paid()->create([
        'order_number' => 'ORD-LISTENER-MALFORMED-001',
        'metadata' => [
            'cart_id' => $cartId,
        ],
    ]);

    // Inject a malformed owner tuple directly into the DB, bypassing the Order model guard.
    // This simulates data corruption or a future migration that invalidates stored owner tuples.
    $bogusType = 'Nonexistent\\OwnerModel\\ThatDoesNotExist';
    $bogusId = Str::uuid()->toString();
    DB::table($order->getTable())->where('id', $order->id)->update([
        'owner_type' => $bogusType,
        'owner_id' => $bogusId,
    ]);
    $order = $order->fresh();

    event(new CommissionAttributionRequired($order));

    expect(AffiliateConversion::query()->count())->toBe(0);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('affiliates.order_commission_skipped_malformed_owner_tuple', \Mockery::on(function (array $context) use ($order, $bogusType): bool {
            return ($context['order_id'] ?? null) === $order->id
                && ($context['owner_type'] ?? null) === $bogusType
                && array_key_exists('message', $context);
        }));
});
