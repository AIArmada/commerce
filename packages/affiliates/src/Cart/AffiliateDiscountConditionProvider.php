<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Cart;

use AIArmada\Affiliates\Features\Attribution\Api\AttributionLookup;
use AIArmada\Affiliates\Features\Identity\Api\CustomerDiscount;
use AIArmada\Affiliates\Features\Identity\Api\CustomerDiscountReader;
use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Runtime\ConditionProviderInterface;

/**
 * Provides affiliate-specific discount conditions for the cart.
 *
 * This class bridges the Affiliates package with the Cart package,
 * applying affiliate customer discounts (if configured) as cart conditions.
 *
 * Note: This is separate from commission tracking - this provides customer-facing
 * discounts that affiliates can offer to their referrals.
 */
final readonly class AffiliateDiscountConditionProvider implements ConditionProviderInterface
{
    private const string CONDITION_TYPE = 'affiliate_discount';

    private const int PRIORITY = 120;

    public function __construct(
        private CustomerDiscountReader $discounts,
        private AttributionLookup $attributions,
    ) {}

    /**
     * Get affiliate discount conditions applicable to the cart.
     *
     * Reads the active persisted cart attribution and creates discount conditions
     * if the affiliate has customer discount configuration.
     *
     * @return array<CartCondition>
     */
    public function getConditionsFor(Cart $cart): array
    {
        if (! config('affiliates.cart.customer_discounts_enabled', false)) {
            return [];
        }

        $attribution = $this->attributions->attached($cart->getIdentifier(), $cart->instance());
        $discount = $attribution === null ? null : $this->discounts->find($attribution->affiliateId);

        return $discount === null ? [] : [$this->createCondition($discount)];
    }

    /**
     * Validate that an affiliate discount condition is still applicable.
     */
    public function validate(CartCondition $condition, Cart $cart): bool
    {
        if ($condition->getType() !== self::CONDITION_TYPE) {
            return true;
        }

        $expected = $this->getConditionsFor($cart)[0] ?? null;

        if ($expected === null) {
            return false;
        }

        $actualAttributes = $condition->getAttributes();
        $expectedAttributes = $expected->getAttributes();
        ksort($actualAttributes);
        ksort($expectedAttributes);

        return $condition->getName() === $expected->getName()
            && $condition->getValue() === $expected->getValue()
            && $condition->getOrder() === $expected->getOrder()
            && $condition->getTargetDefinition()->toArray() === $expected->getTargetDefinition()->toArray()
            && $actualAttributes === $expectedAttributes
            && $condition->getRules() === null;
    }

    /**
     * Get the condition type identifier.
     */
    public function getType(): string
    {
        return self::CONDITION_TYPE;
    }

    /**
     * Get the priority for condition application.
     * Affiliate discounts are applied after vouchers (priority 120).
     */
    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    private function createCondition(CustomerDiscount $discount): CartCondition
    {
        $value = $discount->type === 'percentage'
            ? '-' . ($discount->value / 100) . '%'
            : '-' . (string) $discount->value;

        return new CartCondition(
            name: 'affiliate_discount_' . $discount->affiliateCode,
            type: self::CONDITION_TYPE,
            target: [
                'scope' => ConditionScope::CART->value,
                'phase' => ConditionPhase::CART_SUBTOTAL->value,
                'application' => ConditionApplication::AGGREGATE->value,
            ],
            value: $value,
            attributes: [
                'affiliate_id' => $discount->affiliateId,
                'affiliate_code' => $discount->affiliateCode,
                'affiliate_name' => $discount->affiliateName,
                'discount_type' => $discount->type,
                'discount_value' => $discount->value,
                'description' => sprintf('Affiliate discount from %s', $discount->affiliateName),
            ],
            order: self::PRIORITY,
        );
    }
}
