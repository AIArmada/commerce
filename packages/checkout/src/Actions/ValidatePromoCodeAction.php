<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Checkout\Data\PromoCodeValidationResult;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Promotions\Contracts\PromotionServiceInterface;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Vouchers\Contracts\VoucherServiceInterface;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Services\VoucherDiscountCalculator;
use Throwable;

final class ValidatePromoCodeAction
{
    public function __construct(
        private readonly ?VoucherDiscountCalculator $discountCalculator = null,
    ) {}

    /**
     * @param  array<string, mixed>|Cart  $context  Cart or array with 'subtotal' key
     */
    public function handle(string $code, array | Cart $context, string $currency = 'MYR'): PromoCodeValidationResult
    {
        $code = mb_trim($code);

        if ($code === '') {
            return PromoCodeValidationResult::invalid('Enter a promo code to apply.');
        }

        $subtotal = $this->resolveSubtotal($context);

        $voucherResult = $this->validateVoucher($code, $context, $subtotal, $currency);

        if ($voucherResult !== null) {
            return $voucherResult;
        }

        $promotionResult = $this->validatePromotion($code, $context, $subtotal, $currency);

        if ($promotionResult !== null) {
            return $promotionResult;
        }

        return PromoCodeValidationResult::invalid('This code is not valid. Check the spelling or try another code.');
    }

    private function validateVoucher(string $code, array | Cart $context, int $subtotal, string $currency): ?PromoCodeValidationResult
    {
        if (! interface_exists(VoucherServiceInterface::class)) {
            return null;
        }

        $voucherService = app(VoucherServiceInterface::class);

        try {
            $validation = $voucherService->validate($code, $context);
        } catch (Throwable) {
            return null;
        }

        $voucherData = $this->resolveVoucherData($validation, $code, $voucherService);

        if ($voucherData === null) {
            return null;
        }

        if (! $voucherData->isActive() || ! $voucherData->isWithinDateRange()) {
            return PromoCodeValidationResult::invalid('This voucher is not active.');
        }

        $discount = $this->calculateDiscount($voucherData, $subtotal, $context);

        if ($discount <= 0) {
            return PromoCodeValidationResult::invalid('This voucher cannot be applied to your cart.');
        }

        $label = match ($voucherData->type) {
            VoucherType::Fixed => '-' . MoneyFormatter::formatMinor($discount, $voucherData->currency),
            VoucherType::Percentage => '-' . ($voucherData->value / 100) . '%',
            VoucherType::FreeShipping => 'Free Shipping',
            default => '-' . MoneyFormatter::formatMinor($discount, $voucherData->currency),
        };

        return PromoCodeValidationResult::valid(
            discount: $discount,
            type: 'voucher',
            label: $label,
            name: $voucherData->name,
        );
    }

    private function validatePromotion(string $code, array | Cart $context, int $subtotal, string $currency): ?PromoCodeValidationResult
    {
        if (! interface_exists(PromotionServiceInterface::class)) {
            return null;
        }

        try {
            $targetingContext = $context instanceof Cart
                ? TargetingContext::fromCart($context, ['currency' => $currency])
                : new TargetingContext(
                    cart: (object) ['subtotal' => $subtotal],
                    metadata: ['currency' => $currency],
                );

            $promotionService = app(PromotionServiceInterface::class);
            $promotion = $promotionService->findApplicableCodePromotion($code, $targetingContext);
        } catch (Throwable) {
            return null;
        }

        if (! $promotion instanceof Promotion) {
            return null;
        }

        $discount = $promotion->calculateDiscount($subtotal);

        if ($discount <= 0) {
            return null;
        }

        $label = match ($promotion->type->value) {
            'percentage' => '-' . $promotion->discount_value . '%',
            default => '-' . MoneyFormatter::formatMinor($discount, $currency),
        };

        return PromoCodeValidationResult::valid(
            discount: $discount,
            type: 'promotion',
            label: $label,
            name: $promotion->name,
        );
    }

    private function resolveVoucherData(
        VoucherValidationResult | array $validation,
        string $code,
        VoucherServiceInterface $voucherService,
    ): ?VoucherData {
        if ($validation instanceof VoucherValidationResult) {
            if (! $validation->isValid) {
                return null;
            }

            return $voucherService->find($code);
        }

        if (is_array($validation)) {
            if (! ($validation['valid'] ?? false)) {
                return null;
            }

            $voucherArray = $validation['voucher'] ?? null;

            if (! is_array($voucherArray)) {
                return null;
            }

            return VoucherData::fromArray($voucherArray);
        }

        return null;
    }

    private function calculateDiscount(VoucherData $voucherData, int $subtotal, array | Cart $context): int
    {
        $calculator = $this->discountCalculator ?? app(VoucherDiscountCalculator::class);
        $cart = $context instanceof Cart ? $context : null;

        return $calculator->calculate($voucherData, $subtotal, $cart);
    }

    private function resolveSubtotal(array | Cart $context): int
    {
        if ($context instanceof Cart) {
            return (int) $context->getRawSubtotalWithoutConditions();
        }

        return (int) ($context['subtotal'] ?? 0);
    }
}
