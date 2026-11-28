<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Contracts\OrderMetadataWriter;
use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Orders\Models\Order;
use AIArmada\Promotions\Listeners\MarkPromotionAsUsedOnFulfillment;
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
    $listener = new MarkPromotionAsUsedOnFulfillment;

    $listener->handle(new OrderFulfillmentRequired(order: $order, transactionId: 'txn_1', gateway: 'chip'));
    $listener->handle(new OrderFulfillmentRequired(order: $order->fresh(), transactionId: 'txn_1', gateway: 'chip'));

    expect($promotion->fresh()->usage_count)->toBe(1);
});

test('stamp persistence failure rolls back the promotion usage increment', function (): void {
    $owner = User::factory()->create();
    $promotion = OwnerContext::withOwner($owner, fn (): Promotion => Promotion::factory()->active()->create());
    $order = persistedOrderWithPromotion($owner, $promotion);
    $event = new OrderFulfillmentRequired(order: $order, transactionId: 'txn_1', gateway: 'chip');

    $writer = Mockery::mock(OrderMetadataWriter::class);
    $writer->shouldReceive('merge')->once()
        ->with((string) $order->getKey(), ['promotions_usage_counted' => [(string) $promotion->getKey()]])
        ->andThrow(new RuntimeException('stamp failed'));
    app()->instance(OrderMetadataWriter::class, $writer);

    expect(fn () => (new MarkPromotionAsUsedOnFulfillment)->handle($event))
        ->toThrow(RuntimeException::class, 'stamp failed');

    OwnerContext::withOwner($owner, function () use ($promotion, $order): void {
        expect($promotion->refresh()->usage_count)->toBe(0)
            ->and($order->refresh()->metadata)->not->toHaveKey('promotions_usage_counted');
    });
});
