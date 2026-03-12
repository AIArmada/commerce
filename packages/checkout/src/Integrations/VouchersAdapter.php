<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Vouchers\Contracts\VoucherServiceInterface;
use AIArmada\Vouchers\Data\VoucherValidationResult;

final class VouchersAdapter
{
    /**
     * Apply vouchers to the checkout session.
     *
     * @param  array<string>  $codes
     * @return array{applied: array<array<string, mixed>>, discount: int}
     */
    public function applyVouchers(CheckoutSession $session, array $codes): array
    {
        if (! class_exists(VoucherServiceInterface::class)) {
            return ['applied' => [], 'discount' => 0];
        }

        $voucherService = app(VoucherServiceInterface::class);

        $applied = [];
        $totalDiscount = 0;
        $allowMultiple = config('checkout.integrations.vouchers.allow_multiple', false);

        foreach ($codes as $code) {
            // Validate voucher
            $validation = $this->normalizeValidationResult($voucherService->validate($code, [
                'customer_id' => $session->customer_id,
                'subtotal' => $session->subtotal,
                'currency' => $session->currency,
            ]), $voucherService, $code);

            if (! $validation['valid']) {
                continue;
            }

            // Calculate discount
            $voucher = $validation['voucher'];
            $discount = $this->calculateVoucherDiscount($voucher, $session);

            if ($discount > 0) {
                // Reserve the voucher
                $voucherService->reserve($code, $session->id);

                $applied[] = [
                    'voucher_id' => $voucher['id'],
                    'code' => $code,
                    'type' => $voucher['type'],
                    'discount' => $discount,
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
        if (! class_exists(VoucherServiceInterface::class)) {
            return ['valid' => false, 'message' => 'Vouchers not available', 'voucher' => null];
        }

        $voucherService = app(VoucherServiceInterface::class);

        return $this->normalizeValidationResult($voucherService->validate($code, [
            'customer_id' => $session->customer_id,
            'subtotal' => $session->subtotal,
            'currency' => $session->currency,
        ]), $voucherService, $code);
    }

    /**
     * Release a voucher reservation.
     */
    public function releaseVoucher(string $code): void
    {
        if (! class_exists(VoucherServiceInterface::class)) {
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
        if (! class_exists(VoucherServiceInterface::class)) {
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
    private function calculateVoucherDiscount(array $voucher, CheckoutSession $session): int
    {
        $type = $voucher['type'] ?? 'fixed';
        $value = $voucher['value'] ?? 0;
        $maxDiscount = $voucher['max_discount'] ?? null;
        $subtotal = $session->subtotal;

        $discount = match ($type) {
            'percentage' => (int) round($subtotal * ($value / 100)),
            'fixed' => min($value, $subtotal),
            default => 0,
        };

        // Apply max discount cap if set
        if ($maxDiscount !== null && $discount > $maxDiscount) {
            $discount = $maxDiscount;
        }

        return $discount;
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
}
