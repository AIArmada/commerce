<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Checkout\Contracts\DiscountProvider;
use AIArmada\Checkout\Data\DiscountCommitment;
use AIArmada\Checkout\Data\DiscountProposal;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Integrations\PromotionsAdapter;
use AIArmada\Checkout\Integrations\VouchersAdapter;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\DiscountCompositionService;

final class ApplyDiscountsStep extends AbstractCheckoutStep
{
    /** @var array<string, DiscountCommitment>|null */
    private ?array $activeCommitments = null;

    public function __construct(
        private readonly ?PromotionsAdapter $promotionsAdapter = null,
        private readonly ?VouchersAdapter $vouchersAdapter = null,
        private readonly ?CartManagerInterface $cartManager = null,
    ) {}

    private function compositionService(): DiscountCompositionService
    {
        $providers = [];

        if ($this->promotionsAdapter instanceof DiscountProvider) {
            $providers[] = $this->promotionsAdapter;
        }

        if ($this->vouchersAdapter instanceof DiscountProvider) {
            $providers[] = $this->vouchersAdapter;
        }

        return new DiscountCompositionService($providers);
    }

    public function getIdentifier(): string
    {
        return 'apply_discounts';
    }

    public function getName(): string
    {
        return 'Apply Discounts';
    }

    /** @return array<string> */
    public function getDependencies(): array
    {
        return ['calculate_pricing'];
    }

    public function canSkip(CheckoutSession $session): bool
    {
        $promotionsEnabled = config('checkout.integrations.promotions.enabled', true)
            && $this->promotionsAdapter !== null;
        $vouchersEnabled = config('checkout.integrations.vouchers.enabled', true)
            && $this->vouchersAdapter !== null;

        return ! $promotionsEnabled && ! $vouchersEnabled;
    }

    public function handle(CheckoutSession $session): StepResult
    {
        $composition = $this->compositionService();

        $discountData = $this->buildDiscountData($session);
        $result = $composition->evaluate($session, $discountData);

        $totalDiscount = $result['totalDiscount'];
        $allocations = $result['allocations'];

        $this->activeCommitments = $composition->commit($session, $allocations);

        $discountData['allocations'] = array_map(
            fn (DiscountProposal $p) => $p->toArray(),
            $allocations,
        );
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
            'allocations_count' => count($allocations),
        ]);
    }

    public function rollback(CheckoutSession $session): void
    {
        if ($this->activeCommitments !== null && $this->activeCommitments !== []) {
            $composition = $this->compositionService();
            $composition->release($session, $this->activeCommitments);
            $this->activeCommitments = null;
        }

        $session->update([
            'discount_data' => [],
            'discount_total' => 0,
        ]);

        $session->calculateTotals();
        $session->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDiscountData(CheckoutSession $session): array
    {
        $existing = $session->discount_data ?? [];
        $snapshot = $session->cart_snapshot ?? [];

        return [
            'voucher_codes' => $existing['voucher_codes']
                ?? data_get($snapshot, 'metadata.voucher_codes', []),
        ];
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

        $metadata = $cart->getAllMetadata();
        $conditions = $cart->getConditions()->toArray();
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
