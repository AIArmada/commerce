<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Cart;

use AIArmada\Affiliates\Data\AffiliateData;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionLookup;
use AIArmada\Affiliates\Features\Upline\Api\RankDirectory;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Queries\AffiliateLookupQuery;
use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Contracts\ConditionProviderInterface;

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
        private AffiliateLookupQuery $affiliateLookup,
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
        $affiliate = $attribution === null ? null : $this->affiliateLookup->findById($attribution->affiliateId);

        if (! $affiliate instanceof Affiliate || ! $affiliate->isActive()) {
            return [];
        }

        $affiliateData = $affiliate->toData();

        $discount = $this->getAffiliateDiscountFromModel($affiliate);

        if ($discount === null) {
            return [];
        }

        return [$this->createConditionFromAffiliateData($affiliateData, $discount)];
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

    /**
     * Get discount configuration from Affiliate model.
     *
     * @return array{type: string, value: int}|null
     */
    private function getAffiliateDiscountFromModel(Affiliate $affiliate): ?array
    {
        $metadata = $affiliate->metadata ?? [];

        // Check for customer discount in affiliate metadata
        $customerDiscount = $metadata['customer_discount'] ?? null;

        if (! is_array($customerDiscount)) {
            $customerDiscount = app(RankDirectory::class)->customerDiscount($affiliate->rank_id);
        }

        if ($customerDiscount === null) {
            return null;
        }

        $value = filter_var($customerDiscount['value'] ?? null, FILTER_VALIDATE_INT);
        $type = $customerDiscount['type'] ?? 'percentage';

        if (! in_array($type, ['percentage', 'fixed'], true) || $value === false || $value <= 0 || ($type === 'percentage' && $value > 10000)) {
            return null;
        }

        return [
            'type' => $type,
            'value' => $value,
        ];
    }

    /**
     * Create a CartCondition from AffiliateData.
     *
     * @param  array{type: string, value: int}  $discount
     */
    private function createConditionFromAffiliateData(AffiliateData $affiliateData, array $discount): CartCondition
    {
        $value = $this->formatDiscountValue($discount);

        return new CartCondition(
            name: 'affiliate_discount_' . $affiliateData->code,
            type: self::CONDITION_TYPE,
            target: $this->buildTargetDefinition(),
            value: $value,
            attributes: $this->buildAttributesFromData($affiliateData, $discount),
            order: self::PRIORITY
        );
    }

    /**
     * Format discount value for cart condition.
     *
     * @param  array{type: string, value: int}  $discount
     */
    private function formatDiscountValue(array $discount): string
    {
        if ($discount['type'] === 'percentage') {
            // Value is in basis points (e.g., 500 = 5%)
            $percentage = $discount['value'] / 100;

            return '-' . $percentage . '%';
        }

        // Fixed amount in minor units (cents)
        return '-' . (string) $discount['value'];
    }

    /**
     * Build the target definition for the condition.
     *
     * @return array<string, mixed>
     */
    private function buildTargetDefinition(): array
    {
        return [
            'scope' => ConditionScope::CART->value,
            'phase' => ConditionPhase::CART_SUBTOTAL->value,
            'application' => ConditionApplication::AGGREGATE->value,
        ];
    }

    /**
     * Build condition attributes from AffiliateData.
     *
     * @param  array{type: string, value: int}  $discount
     * @return array<string, mixed>
     */
    private function buildAttributesFromData(AffiliateData $affiliateData, array $discount): array
    {
        return [
            'affiliate_id' => $affiliateData->id,
            'affiliate_code' => $affiliateData->code,
            'affiliate_name' => $affiliateData->name,
            'discount_type' => $discount['type'],
            'discount_value' => $discount['value'],
            'description' => sprintf(
                'Affiliate discount from %s',
                $affiliateData->name
            ),
        ];
    }
}
