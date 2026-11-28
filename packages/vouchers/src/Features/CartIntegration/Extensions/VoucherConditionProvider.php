<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\Extensions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Runtime\ConditionProviderInterface;
use AIArmada\Vouchers\Concerns\NormalizesVoucherCodes;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Runtime\VoucherServiceInterface;
use AIArmada\Vouchers\Support\VoucherCartMetadata;

/**
 * Provides voucher-based cart conditions.
 *
 * This class bridges the Voucher package with the Cart package,
 * converting applied vouchers into CartCondition objects.
 */
final readonly class VoucherConditionProvider implements ConditionProviderInterface
{
    use NormalizesVoucherCodes;

    private const string CONDITION_TYPE = 'voucher';

    private const int PRIORITY = 100;

    public function __construct(
        private VoucherServiceInterface $voucherService
    ) {}

    /**
     * Get voucher conditions applicable to the cart.
     *
     * Reads voucher codes from cart metadata and creates conditions
     * for each valid voucher.
     *
     * @return array<CartCondition>
     */
    public function getConditionsFor(Cart $cart): array
    {
        $conditions = [];

        foreach ($this->metadataCodes($cart) as $code) {
            if ($this->hasDirectCondition($cart, $code)) {
                continue;
            }

            $validationResult = $this->voucherService->validate($code, $cart);
            $snapshot = $validationResult->getDetail('voucher');

            if (! $validationResult->isValid || ! is_array($snapshot)
                || ! is_string($snapshot['id'] ?? null) || $snapshot['id'] === '') {
                continue;
            }

            $condition = $this->createConditionFromVoucher(VoucherData::fromArray($snapshot));

            if ($condition !== null) {
                $conditions[] = $condition;
            }
        }

        return $conditions;
    }

    /**
     * Validate that a condition is still applicable.
     *
     * Called during checkout to ensure vouchers are still valid.
     */
    public function validate(CartCondition $condition, Cart $cart): bool
    {
        if ($condition->getType() !== self::CONDITION_TYPE) {
            return true;
        }

        $code = $condition->getAttribute('voucher_code');
        $voucherId = $condition->getAttribute('voucher_id');

        if (! is_string($code) || ! is_string($voucherId) || $voucherId === ''
            || ! in_array($this->normalizeCode($code), $this->metadataCodes($cart), true)
            || $this->hasDirectCondition($cart, $this->normalizeCode($code))) {
            return false;
        }

        $validationResult = $this->voucherService->validate($code, $cart);

        return $validationResult->matchesVoucher($voucherId)
            && $validationResult->matchesTerms($condition->getAttribute('voucher_data'));
    }

    /** @return list<string> */
    private function metadataCodes(Cart $cart): array
    {
        $raw = $cart->getMetadata(VoucherCartMetadata::VOUCHER_CODES, []);
        $codes = [];

        if (is_array($raw)) {
            foreach ($raw as $code) {
                if (is_string($code) && mb_trim($code) !== '') {
                    $codes[] = $this->normalizeCode($code);
                }
            }
        }

        return array_values(array_unique($codes));
    }

    private function hasDirectCondition(Cart $cart, string $code): bool
    {
        foreach ($cart->getDynamicConditions() as $condition) {
            $candidate = $condition->getAttribute('voucher_code');

            if ($condition->getType() === self::CONDITION_TYPE && is_string($candidate)
                && $this->normalizeCode($candidate) === $code) {
                return true;
            }
        }

        return false;
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
     * Vouchers are applied after shipping (50-99) but before tax (150-199).
     */
    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    /**
     * Create a CartCondition from voucher data.
     */
    private function createConditionFromVoucher(VoucherData $voucher): ?CartCondition
    {
        $value = $this->calculateConditionValue($voucher);

        if ($value === null) {
            return null;
        }

        return new CartCondition(
            name: 'voucher_' . $voucher->code,
            type: self::CONDITION_TYPE,
            target: $this->buildTargetDefinition($voucher),
            value: $value,
            attributes: $this->buildAttributes($voucher),
            order: self::PRIORITY
        );
    }

    /**
     * Calculate the condition value based on voucher type.
     *
     * Note: Percentage values are stored as basis points (e.g., 1250 = 12.5%).
     * Fixed values are stored as cents (e.g., 1000 = $10.00).
     */
    private function calculateConditionValue(VoucherData $voucher): ?string
    {
        return match ($voucher->type) {
            VoucherType::Fixed => '-' . (string) $voucher->value,
            VoucherType::Percentage => $this->formatPercentageValue($voucher->value),
            VoucherType::FreeShipping => '-100%',
            default => null,
        };
    }

    /**
     * Format a basis points value as a percentage string.
     *
     * @param  int  $basisPoints  Value in basis points (e.g., 1250 = 12.5%)
     */
    private function formatPercentageValue(int $basisPoints): string
    {
        $percentage = $basisPoints / 100;

        if ($percentage === (int) $percentage) {
            return '-' . (int) $percentage . '%';
        }

        return '-' . mb_rtrim(mb_rtrim(number_format($percentage, 2, '.', ''), '0'), '.') . '%';
    }

    /**
     * Build the target definition for the condition.
     *
     * @return array<string, mixed>
     */
    private function buildTargetDefinition(VoucherData $voucher): array
    {
        $phase = match ($voucher->type) {
            VoucherType::FreeShipping => ConditionPhase::SHIPPING,
            default => ConditionPhase::CART_SUBTOTAL,
        };

        return [
            'scope' => ConditionScope::CART->value,
            'phase' => $phase->value,
            'application' => ConditionApplication::AGGREGATE->value,
        ];
    }

    /**
     * Build condition attributes from voucher data.
     *
     * @return array<string, mixed>
     */
    private function buildAttributes(VoucherData $voucher): array
    {
        return [
            'voucher_id' => $voucher->id,
            'voucher_data' => $voucher->toArray(),
            'voucher_code' => $voucher->code,
            'voucher_type' => $voucher->type->value,
            'description' => $voucher->description,
            'min_cart_value' => $voucher->minCartValue,
            'max_discount' => $voucher->maxDiscount,
            'currency' => $voucher->currency,
        ];
    }
}
