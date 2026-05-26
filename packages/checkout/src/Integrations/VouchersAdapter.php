<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations;

use AIArmada\Cart\Cart;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Support\CheckoutCartResolver;
use AIArmada\Vouchers\Contracts\VoucherServiceInterface;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Services\VoucherDiscountCalculator;
use Illuminate\Support\Facades\Event;

final class VouchersAdapter
{
    public function __construct(
        private readonly ?CheckoutCartResolver $cartResolver = null,
        private readonly ?DiscountCodeResolver $discountCodeResolver = null,
    ) {}

    /**
     * Apply vouchers to the checkout session.
     *
     * @param  array<string>  $codes
     * @return array{applied: array<array<string, mixed>>, discount: int}
     */
    public function applyVouchers(CheckoutSession $session, array $codes): array
    {
        if (! interface_exists(VoucherServiceInterface::class)) {
            return ['applied' => [], 'discount' => 0];
        }

        $voucherService = app(VoucherServiceInterface::class);
        $validationContext = $this->cartResolver()->resolveVoucherValidationContext($session);
        $liveCart = $validationContext instanceof Cart ? $validationContext : null;
        $codes = $this->mergeUnifiedVoucherCodes($session, $codes);

        $applied = [];
        $totalDiscount = 0;
        $allowMultiple = config('checkout.integrations.vouchers.allow_multiple', false);

        foreach ($codes as $code) {
            // Validate voucher
            $validation = $this->normalizeValidationResult(
                $voucherService->validate($code, $validationContext),
                $voucherService,
                $code,
            );

            if (! $validation['valid']) {
                continue;
            }

            // Calculate discount
            $voucher = $validation['voucher'];
            $discount = $this->calculateVoucherDiscount($voucher, (int) $session->subtotal, $liveCart);

            if ($discount > 0) {
                // Reserve the voucher
                $voucherService->reserve($code, $session->id);

                // Dispatch VoucherApplied to trigger AttachAffiliateFromVoucher listener
                if ($liveCart !== null && class_exists(VoucherApplied::class)) {
                    Event::dispatch(new VoucherApplied($liveCart, VoucherData::fromArray($voucher)));
                }

                $applied[] = [
                    'voucher_id' => $voucher['id'],
                    'code' => $code,
                    'type' => $voucher['type'],
                    'discount' => $discount,
                    'promotion_id' => $voucher['promotion_id'] ?? null,
                ];

                $totalDiscount += $discount;

                // Stop if multiple vouchers not allowed
                if (! $allowMultiple) {
                    break;
                }
            }
        }

        return ['applied' => $applied, 'discount' => $totalDiscount];
    }

    /**
     * Validate a voucher code.
     *
     * @return array{valid: bool, message: string|null, voucher: array<string, mixed>|null}
     */
    public function validateVoucher(string $code, CheckoutSession $session): array
    {
        if (! interface_exists(VoucherServiceInterface::class)) {
            return ['valid' => false, 'message' => 'Vouchers not available', 'voucher' => null];
        }

        $voucherService = app(VoucherServiceInterface::class);

        return $this->normalizeValidationResult(
            $voucherService->validate($code, $this->cartResolver()->resolveVoucherValidationContext($session)),
            $voucherService,
            $code,
        );
    }

    /**
     * Release a voucher reservation.
     */
    public function releaseVoucher(string $code): void
    {
        if (! interface_exists(VoucherServiceInterface::class)) {
            return;
        }

        $voucherService = app(VoucherServiceInterface::class);
        $voucherService->release($code);
    }

    /**
     * Redeem vouchers after successful checkout.
     *
     * @param  array<string>  $codes
     */
    public function redeemVouchers(array $codes, string $orderId): void
    {
        if (! interface_exists(VoucherServiceInterface::class)) {
            return;
        }

        $voucherService = app(VoucherServiceInterface::class);

        foreach ($codes as $code) {
            $voucherService->redeem($code, $orderId);
        }
    }

    /**
     * @param  array<string, mixed>  $voucher
     */
    private function calculateVoucherDiscount(array $voucher, int $subtotal, ?Cart $cart): int
    {
        if (! class_exists(VoucherDiscountCalculator::class)) {
            return 0;
        }

        $voucherData = VoucherData::fromArray($voucher);
        $discountCalculator = app(VoucherDiscountCalculator::class);

        return $discountCalculator->calculate($voucherData, $subtotal, $cart);
    }

    /**
     * @param  array{valid: bool, message: string|null, voucher: array<string, mixed>|null}|VoucherValidationResult  $validation
     * @return array{valid: bool, message: string|null, voucher: array<string, mixed>|null}
     */
    private function normalizeValidationResult(
        array | VoucherValidationResult $validation,
        VoucherServiceInterface $voucherService,
        string $code
    ): array {
        if (is_array($validation)) {
            return $validation;
        }

        $voucher = null;

        if ($validation->isValid) {
            $voucherData = $voucherService->find($code);

            if ($voucherData === null) {
                return ['valid' => false, 'message' => 'Voucher not found.', 'voucher' => null];
            }

            $voucher = $voucherData->toArray();
        }

        return [
            'valid' => $validation->isValid,
            'message' => $validation->reason,
            'voucher' => $voucher,
        ];
    }

    /**
     * @param  array<string>  $codes
     * @return array<string>
     */
    private function mergeUnifiedVoucherCodes(CheckoutSession $session, array $codes): array
    {
        $resolvedDiscountCode = $this->discountCodeResolver()->resolve($session);

        if ($resolvedDiscountCode->isVoucher()) {
            array_unshift($codes, $resolvedDiscountCode->code);
        }

        $normalized = array_filter(array_map(
            static fn (mixed $code): ?string => is_string($code) && mb_trim($code) !== '' ? mb_trim($code) : null,
            $codes,
        ));

        return array_values(array_unique($normalized));
    }

    private function cartResolver(): CheckoutCartResolver
    {
        return $this->cartResolver ?? app(CheckoutCartResolver::class);
    }

    private function discountCodeResolver(): DiscountCodeResolver
    {
        return $this->discountCodeResolver ?? app(DiscountCodeResolver::class);
    }
}
