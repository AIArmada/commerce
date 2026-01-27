<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Integrations\PromotionsAdapter;
use AIArmada\Checkout\Integrations\VouchersAdapter;
use AIArmada\Checkout\Models\CheckoutSession;

final class ApplyDiscountsStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly ?PromotionsAdapter $promotionsAdapter = null,
        private readonly ?VouchersAdapter $vouchersAdapter = null,
    ) {}

    public function getIdentifier(): string
    {
        return 'apply_discounts';
    }

    public function getName(): string
    {
        return 'Apply Discounts';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return ['calculate_pricing'];
    }

    public function canSkip(CheckoutSession $session): bool
    {
        // Skip if no discount packages are configured
        $promotionsEnabled = config('checkout.integrations.promotions.enabled', true)
            && $this->promotionsAdapter !== null;
        $vouchersEnabled = config('checkout.integrations.vouchers.enabled', true)
            && $this->vouchersAdapter !== null;

        return ! $promotionsEnabled && ! $vouchersEnabled;
    }

    public function handle(CheckoutSession $session): StepResult
    {
        $discountData = [
            'promotions' => [],
            'vouchers' => [],
            'total_discount' => 0,
        ];

        $totalDiscount = 0;

        // Apply automatic promotions
        if ($this->shouldApplyPromotions()) {
            $promotionResult = $this->applyPromotions($session);
            $discountData['promotions'] = $promotionResult['applied'];
            $totalDiscount += $promotionResult['discount'];
        }

        // Apply vouchers
        if ($this->shouldApplyVouchers()) {
            $voucherResult = $this->applyVouchers($session);
            $discountData['vouchers'] = $voucherResult['applied'];
            $totalDiscount += $voucherResult['discount'];
        }

        $discountData['total_discount'] = $totalDiscount;
        $discountData['applied_at'] = now()->toIso8601String();

        $session->update([
            'discount_data' => $discountData,
            'discount_total' => $totalDiscount,
        ]);

        $session->calculateTotals();
        $session->save();

        return $this->success('Discounts applied', [
            'total_discount' => $totalDiscount,
            'promotions_count' => count($discountData['promotions']),
            'vouchers_count' => count($discountData['vouchers']),
        ]);
    }

    public function rollback(CheckoutSession $session): void
    {
        // Release any voucher reservations
        if ($this->vouchersAdapter !== null) {
            $discountData = $session->discount_data ?? [];
            foreach ($discountData['vouchers'] ?? [] as $voucher) {
                $this->vouchersAdapter->releaseVoucher($voucher['code'] ?? '');
            }
        }

        $session->update([
            'discount_data' => [],
            'discount_total' => 0,
        ]);

        $session->calculateTotals();
        $session->save();
    }

    private function shouldApplyPromotions(): bool
    {
        return config('checkout.integrations.promotions.enabled', true)
            && config('checkout.integrations.promotions.auto_apply', true)
            && $this->promotionsAdapter !== null;
    }

    private function shouldApplyVouchers(): bool
    {
        return config('checkout.integrations.vouchers.enabled', true)
            && $this->vouchersAdapter !== null;
    }

    /**
     * @return array{applied: array<array<string, mixed>>, discount: int}
     */
    private function applyPromotions(CheckoutSession $session): array
    {
        if ($this->promotionsAdapter === null) {
            return ['applied' => [], 'discount' => 0];
        }

        return $this->promotionsAdapter->applyEligiblePromotions($session);
    }

    /**
     * @return array{applied: array<array<string, mixed>>, discount: int}
     */
    private function applyVouchers(CheckoutSession $session): array
    {
        if ($this->vouchersAdapter === null) {
            return ['applied' => [], 'discount' => 0];
        }

        // Vouchers should be pre-selected/attached to session
        $discountData = $session->discount_data ?? [];
        $voucherCodes = $discountData['voucher_codes'] ?? [];

        if (empty($voucherCodes)) {
            return ['applied' => [], 'discount' => 0];
        }

        return $this->vouchersAdapter->applyVouchers($session, $voucherCodes);
    }
}
