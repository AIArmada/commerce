<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations;

use AIArmada\Checkout\Data\ResolvedDiscountCode;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Support\CheckoutCartResolver;
use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Promotions\Contracts\PromotionServiceInterface;
use AIArmada\Vouchers\Contracts\VoucherServiceInterface;
use AIArmada\Vouchers\Data\VoucherValidationResult;

final class DiscountCodeResolver
{
    public function __construct(
        private readonly ?CheckoutCartResolver $cartResolver = null,
    ) {}

    public function resolve(CheckoutSession $session, ?TargetingContext $promotionContext = null): ResolvedDiscountCode
    {
        $promoCode = $this->resolvePromoCode($session);

        if ($promoCode === null) {
            return ResolvedDiscountCode::none();
        }

        $voucher = $this->resolveVoucher($promoCode, $session);

        if (is_array($voucher)) {
            return ResolvedDiscountCode::voucher($promoCode, $voucher);
        }

        if ($promotionContext === null || ! interface_exists(PromotionServiceInterface::class)) {
            return ResolvedDiscountCode::none();
        }

        $promotionService = app(PromotionServiceInterface::class);
        $promotion = $promotionService->findApplicableCodePromotion($promoCode, $promotionContext);

        if (! is_object($promotion)) {
            return ResolvedDiscountCode::none();
        }

        return ResolvedDiscountCode::promotion($promoCode, $promotion);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveVoucher(string $code, CheckoutSession $session): ?array
    {
        if (! interface_exists(VoucherServiceInterface::class)) {
            return null;
        }

        $voucherService = app(VoucherServiceInterface::class);
        $validationContext = $this->cartResolver()->resolveVoucherValidationContext($session);
        $validation = $voucherService->validate($code, $validationContext);

        if (is_array($validation)) {
            $voucher = $validation['voucher'] ?? null;

            return ($validation['valid'] ?? false) && is_array($voucher)
                ? $voucher
                : null;
        }

        if (! $validation instanceof VoucherValidationResult || ! $validation->isValid) {
            return null;
        }

        $voucherData = $voucherService->find($code);

        return $voucherData?->toArray();
    }

    private function resolvePromoCode(CheckoutSession $session): ?string
    {
        $promoCode = data_get($session->billing_data, 'metadata.promo_code')
            ?? data_get($session->cart_snapshot, 'metadata.promo_code');

        if (! is_string($promoCode)) {
            return null;
        }

        $resolved = mb_trim($promoCode);

        return $resolved !== '' ? $resolved : null;
    }

    private function cartResolver(): CheckoutCartResolver
    {
        return $this->cartResolver ?? app(CheckoutCartResolver::class);
    }
}
