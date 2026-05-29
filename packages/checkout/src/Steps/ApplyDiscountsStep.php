<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Integrations\PromotionsAdapter;
use AIArmada\Checkout\Integrations\VouchersAdapter;
use AIArmada\Checkout\Models\CheckoutSession;

final class ApplyDiscountsStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly ?PromotionsAdapter $promotionsAdapter = null,
        private readonly ?VouchersAdapter $vouchersAdapter = null,
        private readonly ?CartManagerInterface $cartManager = null,
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
        $this->refreshCartSnapshot($session);

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

        $discountData = $session->discount_data ?? [];
        $voucherCodes = $discountData['voucher_codes']
            ?? data_get($session->cart_snapshot, 'metadata.voucher_codes', []);

        if (! is_array($voucherCodes)) {
            return ['applied' => [], 'discount' => 0];
        }

        $voucherCodes = array_values(array_filter(array_map(
            static fn (mixed $code): ?string => is_string($code) && mb_trim($code) !== '' ? mb_trim($code) : null,
            $voucherCodes,
        )));

        if (empty($voucherCodes)) {
            return ['applied' => [], 'discount' => 0];
        }

        return $this->vouchersAdapter->applyVouchers($session, $voucherCodes);
    }

    private function refreshCartSnapshot(CheckoutSession $session): void
    {
        $cartManager = $this->cartManager;

        if ($cartManager === null) {
            if (! app()->bound(CartManagerInterface::class)) {
                return;
            }

            $resolvedCartManager = app(CartManagerInterface::class);

            if (! $resolvedCartManager instanceof CartManagerInterface) {
                return;
            }

            $cartManager = $resolvedCartManager;
        }

        $cart = $cartManager->getById($session->cart_id);

        if ($cart === null) {
            return;
        }

        $metadata = method_exists($cart, 'getAllMetadata') ? $cart->getAllMetadata() : [];
        $conditions = method_exists($cart, 'getConditions') ? $cart->getConditions()->toArray() : [];
        $subtotal = $cart->subtotal()->getAmount();
        $total = $cart->total()->getAmount();

        $session->update([
            'cart_snapshot' => [
                'items' => $cart->getItems()->toArray(),
                'metadata' => $metadata,
                'conditions' => $conditions,
                'totals' => [
                    'subtotal' => $subtotal,
                    'total' => $total,
                ],
                'subtotal' => $subtotal,
                'total' => $total,
                'item_count' => $cart->countItems(),
                'captured_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
