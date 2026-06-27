<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingEngineInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Models\Order;
use AIArmada\Promotions\Actions\EvaluatePromotionForCart;
use AIArmada\Promotions\Listeners\MarkPromotionAsUsedOnOrderPlaced;
use AIArmada\Promotions\Listeners\ReevaluatePromotionsOnCartUpdated;
use AIArmada\Promotions\Models\Promotion;
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

    $listener = new MarkPromotionAsUsedOnOrderPlaced;

    $listener->handle(new OrderPaid(
        order: orderWithPromotion($owner, $promotion),
        transactionId: 'txn_123',
        gateway: 'chip',
    ));

    $listener->handle(new OrderPaid(
        order: orderWithPromotion($owner, $otherPromotion),
        transactionId: 'txn_456',
        gateway: 'chip',
    ));

    expect($promotion->fresh()->usage_count)->toBe(1)
        ->and($otherPromotion->fresh()->usage_count)->toBe(0);
});

it('reevaluates only active promotions in the cart owner scope', function (): void {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();

    $engine = new class implements TargetingEngineInterface
    {
        /** @var list<array<string, mixed>> */
        public array $evaluated = [];

        public function registerEvaluator(TargetingRuleEvaluator $evaluator): self
        {
            return $this;
        }

        public function registerEvaluatorsFromContainer(iterable $taggedEvaluators): self
        {
            return $this;
        }

        public function getEvaluator(string $type): ?TargetingRuleEvaluator
        {
            return null;
        }

        public function getEvaluators(): array
        {
            return [];
        }

        public function evaluate(array $targeting, TargetingContextInterface $context): bool
        {
            $this->evaluated[] = $targeting;

            return true;
        }

        public function validate(array $targeting): array
        {
            return [];
        }
    };

    app()->instance(TargetingEngineInterface::class, $engine);

    OwnerContext::withOwner($owner, fn (): Promotion => Promotion::factory()->active()->create([
        'conditions' => ['owner' => 'current'],
    ]));

    OwnerContext::withOwner($otherOwner, fn (): Promotion => Promotion::factory()->active()->create([
        'conditions' => ['owner' => 'other'],
    ]));

    OwnerContext::withOwner($owner, fn (): Promotion => Promotion::factory()->inactive()->create([
        'conditions' => ['owner' => 'inactive'],
    ]));

    $storage = (new InMemoryStorage)->withOwner($owner);
    $cart = new Cart($storage, 'cart-owner');
    $item = new CartItem('sku-1', 'Test Item', 1000, 1);

    $listener = new ReevaluatePromotionsOnCartUpdated(new EvaluatePromotionForCart($engine));
    $listener->handle(new ItemAdded($item, $cart));

    expect($engine->evaluated)->toBe([
        ['owner' => 'current'],
    ]);
});

function orderWithPromotion(User $owner, Promotion $promotion): Order
{
    $order = new Order;
    $order->forceFill([
        'id' => (string) Str::uuid(),
        'order_number' => 'ORD-' . Str::upper(Str::random(8)),
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'promotion_id' => $promotion->id,
    ]);

    return $order;
}
