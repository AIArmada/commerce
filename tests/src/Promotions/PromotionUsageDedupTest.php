<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Models\Order;
use AIArmada\Promotions\Listeners\MarkPromotionAsUsedOnOrderPlaced;
use AIArmada\Promotions\Models\Promotion;
use Illuminate\Support\Str;

function persistedOrderWithPromotion(User $owner, Promotion $promotion): Order
{
    return OwnerContext::withOwner($owner, function () use ($owner, $promotion): Order {
        $session = new CheckoutSession;
        $session->forceFill([
            'id' => (string) Str::uuid(),
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'cart_id' => (string) Str::uuid(),
            'status' => 'completed',
            'currency' => 'MYR',
            'subtotal' => 10000,
            'discount_total' => 1000,
            'discount_data' => [
                'allocations' => [
                    [
                        'provider_key' => 'promotions',
                        'candidate_key' => 'promotion:' . $promotion->id,
                        'requested_amount' => 1000,
                        'meta' => ['promotion_id' => $promotion->id],
                    ],
                ],
                'total_discount' => 1000,
            ],
        ]);
        $session->save();

        $order = new Order;
        $order->forceFill([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'metadata' => ['checkout_session_id' => $session->id],
        ]);
        $order->save();

        return $order;
    });
}

beforeEach(function (): void {
    config()->set('promotions.features.owner.enabled', true);
    config()->set('promotions.features.owner.include_global', false);
    config()->set('promotions.features.owner.auto_assign_on_create', true);
});

it('counts each order only once when payment events are redelivered', function (): void {
    $owner = User::factory()->create();
    $promotion = OwnerContext::withOwner($owner, fn (): Promotion => Promotion::factory()->active()->create());

    $order = persistedOrderWithPromotion($owner, $promotion);
    $listener = new MarkPromotionAsUsedOnOrderPlaced;

    $listener->handle(new OrderPaid(order: $order, transactionId: 'txn_1', gateway: 'chip'));
    $listener->handle(new OrderPaid(order: $order->fresh(), transactionId: 'txn_1', gateway: 'chip'));

    expect($promotion->fresh()->usage_count)->toBe(1);
});
