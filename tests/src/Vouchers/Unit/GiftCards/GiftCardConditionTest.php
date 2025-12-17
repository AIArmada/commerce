<?php

declare(strict_types=1);

namespace Tests\Vouchers\Unit\GiftCards;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\GiftCards\Conditions\GiftCardCondition;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Create a test gift card with given attributes.
 *
 * @param  array<string, mixed>  $attributes
 */
function createTestGiftCard(array $attributes = []): GiftCard
{
    $defaults = [
        'initial_balance' => 10000,
        'current_balance' => 10000,
        'status' => GiftCardStatus::Active,
        'currency' => 'MYR',
    ];

    return GiftCard::create(array_merge($defaults, $attributes));
}

/**
 * Create a test cart.
 */
function createTestCart(string $id = 'test-cart'): Cart
{
    return new Cart(new InMemoryStorage, $id);
}

describe('GiftCardCondition Construction', function (): void {
    it('creates condition from gift card with default order', function (): void {
        $giftCard = createTestGiftCard(['code' => 'GC-TEST-001']);
        $condition = new GiftCardCondition($giftCard);

        expect($condition->getName())->toBe('gift_card_GC-TEST-001')
            ->and($condition->getType())->toBe('gift_card')
            ->and($condition->getOrder())->toBe(100)
            ->and($condition->isDynamic())->toBeTrue()
            ->and($condition->getGiftCard())->toBe($giftCard)
            ->and($condition->getGiftCardCode())->toBe('GC-TEST-001')
            ->and($condition->getGiftCardId())->toBe($giftCard->id);
    });

    it('creates condition with custom order', function (): void {
        $giftCard = createTestGiftCard();
        $condition = new GiftCardCondition($giftCard, order: 50);

        expect($condition->getOrder())->toBe(50);
    });

    it('creates non-dynamic condition when specified', function (): void {
        $giftCard = createTestGiftCard();
        $condition = new GiftCardCondition($giftCard, dynamic: false);

        expect($condition->isDynamic())->toBeFalse()
            ->and($condition->getRules())->toBeNull();
    });

    it('sets correct attributes', function (): void {
        $giftCard = createTestGiftCard([
            'code' => 'GC-ATTR-TEST',
            'initial_balance' => 5000,
            'current_balance' => 5000,
            'currency' => 'USD',
        ]);

        $condition = new GiftCardCondition($giftCard);
        $attributes = $condition->getAttributes();

        expect($attributes['gift_card_id'])->toBe($giftCard->id)
            ->and($attributes['gift_card_code'])->toBe('GC-ATTR-TEST')
            ->and($attributes['available_balance'])->toBe(5000)
            ->and($attributes['currency'])->toBe('USD')
            ->and($attributes['pending_deduction'])->toBe(0);
    });

    it('sets value based on balance', function (): void {
        $giftCard = createTestGiftCard(['current_balance' => 7500]);
        $condition = new GiftCardCondition($giftCard);

        expect($condition->getValue())->toBe('-7500');
    });
});

describe('GiftCardCondition Target Definition', function (): void {
    it('targets grand total phase', function (): void {
        $giftCard = createTestGiftCard();
        $condition = new GiftCardCondition($giftCard);
        $target = $condition->getTargetDefinition()->toArray();

        expect($target['phase'])->toBe(ConditionPhase::GRAND_TOTAL->value);
    });

    it('targets cart scope', function (): void {
        $giftCard = createTestGiftCard();
        $condition = new GiftCardCondition($giftCard);
        $target = $condition->getTargetDefinition()->toArray();

        expect($target['scope'])->toBe(ConditionScope::CART->value);
    });

    it('includes gift card metadata in target', function (): void {
        $giftCard = createTestGiftCard(['code' => 'GC-META-001']);
        $condition = new GiftCardCondition($giftCard);
        $target = $condition->getTargetDefinition()->toArray();

        expect($target['meta']['source'])->toBe('gift_card')
            ->and($target['meta']['gift_card_id'])->toBe($giftCard->id)
            ->and($target['meta']['gift_card_code'])->toBe('GC-META-001');
    });
});

describe('GiftCardCondition Characteristic Methods', function (): void {
    it('reports as discount', function (): void {
        $giftCard = createTestGiftCard();
        $condition = new GiftCardCondition($giftCard);

        expect($condition->isDiscount())->toBeTrue();
    });

    it('reports as not charge', function (): void {
        $giftCard = createTestGiftCard();
        $condition = new GiftCardCondition($giftCard);

        expect($condition->isCharge())->toBeFalse();
    });

    it('reports as not percentage', function (): void {
        $giftCard = createTestGiftCard();
        $condition = new GiftCardCondition($giftCard);

        expect($condition->isPercentage())->toBeFalse();
    });

    it('returns rule factory key', function (): void {
        $giftCard = createTestGiftCard();
        $condition = new GiftCardCondition($giftCard);

        expect($condition->getRuleFactoryKey())->toBe('gift_card');
    });

    it('returns rule factory context', function (): void {
        $giftCard = createTestGiftCard(['code' => 'GC-CONTEXT-001']);
        $condition = new GiftCardCondition($giftCard);
        $context = $condition->getRuleFactoryContext();

        expect($context['gift_card_code'])->toBe('GC-CONTEXT-001')
            ->and($context['gift_card_id'])->toBe($giftCard->id);
    });
});

describe('GiftCardCondition Calculate Deduction', function (): void {
    it('calculates deduction up to balance', function (): void {
        $giftCard = createTestGiftCard(['current_balance' => 5000]);
        $condition = new GiftCardCondition($giftCard);

        expect($condition->calculateDeduction(3000))->toBe(3000)
            ->and($condition->calculateDeduction(5000))->toBe(5000)
            ->and($condition->calculateDeduction(10000))->toBe(5000);
    });

    it('gets available balance', function (): void {
        $giftCard = createTestGiftCard(['current_balance' => 7500]);
        $condition = new GiftCardCondition($giftCard);

        expect($condition->getAvailableBalance())->toBe(7500);
    });
});

describe('GiftCardCondition Pending Deduction', function (): void {
    it('can set pending deduction', function (): void {
        $giftCard = createTestGiftCard();
        $condition = new GiftCardCondition($giftCard);

        $condition->setPendingDeduction(3000);

        expect($condition->getPendingDeduction())->toBe(3000)
            ->and($condition->getValue())->toBe('-3000');
    });

    it('pending deduction starts at zero', function (): void {
        $giftCard = createTestGiftCard();
        $condition = new GiftCardCondition($giftCard);

        expect($condition->getPendingDeduction())->toBe(0);
    });
});

describe('GiftCardCondition Apply', function (): void {
    it('applies deduction and sets pending amount', function (): void {
        $giftCard = createTestGiftCard(['current_balance' => 5000]);
        $condition = new GiftCardCondition($giftCard);

        $result = $condition->apply(8000);

        expect($result)->toBe(3000.0)
            ->and($condition->getPendingDeduction())->toBe(5000);
    });

    it('does not go below zero', function (): void {
        $giftCard = createTestGiftCard(['current_balance' => 10000]);
        $condition = new GiftCardCondition($giftCard);

        $result = $condition->apply(3000);

        expect($result)->toBe(0.0)
            ->and($condition->getPendingDeduction())->toBe(3000);
    });

    it('calculates display value as negative', function (): void {
        $giftCard = createTestGiftCard(['current_balance' => 5000]);
        $condition = new GiftCardCondition($giftCard);

        expect($condition->getCalculatedValue(8000))->toBe(-5000.0)
            ->and($condition->getCalculatedValue(3000))->toBe(-3000.0);
    });
});

describe('GiftCardCondition Validation', function (): void {
    it('validates redeemable gift card', function (): void {
        $giftCard = createTestGiftCard([
            'status' => GiftCardStatus::Active,
            'current_balance' => 5000,
        ]);
        $condition = new GiftCardCondition($giftCard);
        $cart = createTestCart();

        expect($condition->validateGiftCard($cart))->toBeTrue();
    });

    it('validates non-redeemable gift card', function (): void {
        $giftCard = createTestGiftCard([
            'status' => GiftCardStatus::Inactive,
            'current_balance' => 5000,
        ]);
        $condition = new GiftCardCondition($giftCard);
        $cart = createTestCart();

        expect($condition->validateGiftCard($cart))->toBeFalse();
    });

    it('validates expired gift card', function (): void {
        $giftCard = createTestGiftCard([
            'status' => GiftCardStatus::Active,
            'expires_at' => now()->subDay(),
        ]);
        $condition = new GiftCardCondition($giftCard);
        $cart = createTestCart();

        expect($condition->validateGiftCard($cart))->toBeFalse();
    });

    it('validates exhausted gift card', function (): void {
        $giftCard = createTestGiftCard([
            'status' => GiftCardStatus::Exhausted,
            'current_balance' => 0,
        ]);
        $condition = new GiftCardCondition($giftCard);
        $cart = createTestCart();

        expect($condition->validateGiftCard($cart))->toBeFalse();
    });
});

describe('GiftCardCondition CartCondition Conversion', function (): void {
    it('converts to cart condition', function (): void {
        $giftCard = createTestGiftCard(['code' => 'GC-CONVERT-001']);
        $condition = new GiftCardCondition($giftCard);

        $cartCondition = $condition->toCartCondition();

        expect($cartCondition)->toBeInstanceOf(CartCondition::class)
            ->and($cartCondition->getName())->toBe('gift_card_GC-CONVERT-001')
            ->and($cartCondition->getType())->toBe('gift_card');
    });

    it('returns cached cart condition', function (): void {
        $giftCard = createTestGiftCard();
        $condition = new GiftCardCondition($giftCard);

        $first = $condition->toCartCondition();
        $second = $condition->toCartCondition();

        expect($first)->toBe($second);
    });

    it('creates from cart condition', function (): void {
        $giftCard = createTestGiftCard(['code' => 'GC-RESTORE-001']);
        $originalCondition = new GiftCardCondition($giftCard);
        $cartCondition = $originalCondition->toCartCondition();

        $restored = GiftCardCondition::fromCartCondition($cartCondition);

        expect($restored)->not->toBeNull()
            ->and($restored->getGiftCardCode())->toBe('GC-RESTORE-001')
            ->and($restored->getGiftCardId())->toBe($giftCard->id);
    });

    it('returns null for non-gift-card condition', function (): void {
        $cartCondition = new CartCondition(
            name: 'OTHER',
            type: 'discount',
            target: [
                'scope' => 'cart',
                'phase' => 'cart_subtotal',
                'application' => 'aggregate',
            ],
            value: '-10%'
        );

        $result = GiftCardCondition::fromCartCondition($cartCondition);

        expect($result)->toBeNull();
    });

    it('returns null when gift card not found', function (): void {
        $cartCondition = new CartCondition(
            name: 'gift_card_NOTEXIST',
            type: 'gift_card',
            target: [
                'scope' => 'cart',
                'phase' => 'grand_total',
                'application' => 'aggregate',
            ],
            value: '-1000',
            attributes: ['gift_card_id' => 'non-existent-id']
        );

        $result = GiftCardCondition::fromCartCondition($cartCondition);

        expect($result)->toBeNull();
    });

    it('returns null when gift card id missing', function (): void {
        $cartCondition = new CartCondition(
            name: 'gift_card_TEST',
            type: 'gift_card',
            target: [
                'scope' => 'cart',
                'phase' => 'grand_total',
                'application' => 'aggregate',
            ],
            value: '-1000',
            attributes: []
        );

        $result = GiftCardCondition::fromCartCondition($cartCondition);

        expect($result)->toBeNull();
    });
});

describe('GiftCardCondition Serialization', function (): void {
    it('converts to array', function (): void {
        $giftCard = createTestGiftCard([
            'code' => 'GC-ARRAY-001',
            'current_balance' => 7500,
            'status' => GiftCardStatus::Active,
        ]);
        $condition = new GiftCardCondition($giftCard);

        $array = $condition->toArray();

        expect($array['name'])->toBe('gift_card_GC-ARRAY-001')
            ->and($array['type'])->toBe('gift_card')
            ->and($array['value'])->toBe('-7500')
            ->and($array['is_discount'])->toBeTrue()
            ->and($array['is_charge'])->toBeFalse()
            ->and($array['is_percentage'])->toBeFalse()
            ->and($array['gift_card']['code'])->toBe('GC-ARRAY-001')
            ->and($array['gift_card']['available_balance'])->toBe(7500)
            ->and($array['gift_card']['status'])->toBe(GiftCardStatus::Active->value);
    });

    it('converts to json', function (): void {
        $giftCard = createTestGiftCard(['code' => 'GC-JSON-001']);
        $condition = new GiftCardCondition($giftCard);

        $json = $condition->toJson();

        expect($json)->toBeString()
            ->and($json)->toContain('GC-JSON-001')
            ->and($json)->toContain('gift_card');
    });

    it('json serializes correctly', function (): void {
        $giftCard = createTestGiftCard(['code' => 'GC-SERIALIZE-001']);
        $condition = new GiftCardCondition($giftCard);

        $serialized = $condition->jsonSerialize();

        expect($serialized)->toBeArray()
            ->and($serialized['name'])->toBe('gift_card_GC-SERIALIZE-001');
    });
});

describe('GiftCardCondition Get Attribute', function (): void {
    it('gets specific attribute', function (): void {
        $giftCard = createTestGiftCard(['currency' => 'EUR']);
        $condition = new GiftCardCondition($giftCard);

        expect($condition->getAttribute('currency'))->toBe('EUR');
    });

    it('returns default for missing attribute', function (): void {
        $giftCard = createTestGiftCard();
        $condition = new GiftCardCondition($giftCard);

        expect($condition->getAttribute('nonexistent', 'default'))->toBe('default');
    });
});
