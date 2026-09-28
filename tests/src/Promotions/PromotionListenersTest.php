<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Promotions\Listeners\MarkPromotionAsUsedOnFulfillment;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Promotions\PromotionsServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('promotions.features.owner.enabled', true);
    config()->set('promotions.features.owner.include_global', false);
    config()->set('promotions.features.owner.auto_assign_on_create', true);
});

it('increments usage count for the paid order promotion in the order owner scope', function (): void {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();

    $promotion = OwnerContext::withOwner($owner, fn (): Promotion => Promotion::factory()->active()->create());
    $otherPromotion = OwnerContext::withOwner($otherOwner, fn (): Promotion => Promotion::factory()->active()->create());

    $listener = new MarkPromotionAsUsedOnFulfillment;

    $listener->handle(new OrderFulfillmentRequired(
        order: orderWithPromotion($owner, $promotion),
        transactionId: 'txn_123',
        gateway: 'chip',
    ));

    $listener->handle(new OrderFulfillmentRequired(
        order: orderWithPromotion($owner, $otherPromotion),
        transactionId: 'txn_456',
        gateway: 'chip',
    ));

    expect($promotion->fresh()->usage_count)->toBe(1)
        ->and($otherPromotion->fresh()->usage_count)->toBe(0);
});

it('counts usage for free orders through the fulfillment event', function (): void {
    $owner = User::factory()->create();
    $promotion = OwnerContext::withOwner($owner, fn (): Promotion => Promotion::factory()->active()->create());

    $listener = new MarkPromotionAsUsedOnFulfillment;

    $listener->handle(new OrderFulfillmentRequired(
        order: orderWithPromotion($owner, $promotion),
        transactionId: 'free-order-id',
        gateway: 'free',
    ));

    expect($promotion->fresh()->usage_count)->toBe(1);
});

it('registers promotion usage on the fulfillment event', function (): void {
    // PromotionsServiceProvider is not auto-loaded by the suite TestCase.
    app()->register(PromotionsServiceProvider::class);

    expect(Event::getRawListeners()[OrderFulfillmentRequired::class] ?? [])
        ->toContain(MarkPromotionAsUsedOnFulfillment::class);
});

function orderWithPromotion(User $owner, Promotion $promotion): Order
{
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
                    'meta' => [
                        'promotion_id' => $promotion->id,
                    ],
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
        'status' => PendingPayment::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 9000,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'metadata' => ['checkout_session_id' => $session->id],
    ]);
    OwnerContext::withOwner($owner, static fn (): mixed => $order->save());

    return $order;
}
