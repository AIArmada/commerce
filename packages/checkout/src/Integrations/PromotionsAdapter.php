<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingEngineInterface;
use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Promotions\Contracts\PromotionServiceInterface;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Vouchers\Contracts\VoucherServiceInterface;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use Illuminate\Support\Collection;

final class PromotionsAdapter
{
    public function __construct(
        private readonly ?TargetingEngineInterface $targetingEngine = null,
    ) {}

    /**
     * Apply eligible promotions to the checkout session.
     *
     * @return array{applied: array<array<string, mixed>>, discount: int}
     */
    public function applyEligiblePromotions(CheckoutSession $session): array
    {
        if (! interface_exists(PromotionServiceInterface::class) || ! class_exists(TargetingContext::class) || ! class_exists(Promotion::class)) {
            return ['applied' => [], 'discount' => 0];
        }

        $subtotal = max(0, (int) $session->subtotal);

        $promotionService = app(PromotionServiceInterface::class);
        $context = $this->makeContext($session);
        $promotions = $promotionService->getApplicablePromotions($context);
        $codePromotion = $this->resolveCodePromotion($session, $context);

        if ($codePromotion !== null) {
            $promotions = $promotions
                ->push($codePromotion)
                ->sortByDesc('priority')
                ->unique('id')
                ->values();
        }

        $result = $this->calculateDiscounts($promotions, $subtotal);

        return [
            'applied' => $this->formatAppliedPromotions($result['applied']),
            'discount' => $result['discount'],
        ];
    }

    private function makeContext(CheckoutSession $session): TargetingContext
    {
        $cartSnapshot = $session->cart_snapshot ?? [];
        $items = $cartSnapshot['items'] ?? [];

        return new TargetingContext(
            cart: (object) [
                'items' => $items,
                'subtotal' => $session->subtotal,
                'total' => $session->grand_total,
                'quantity' => count($items),
            ],
            user: null,
            request: null,
            metadata: [
                'customer_id' => $session->customer_id,
                'currency' => $session->currency,
                'checkout_session_id' => $session->id,
            ],
        );
    }

    /**
     * @param  Collection<int, Promotion>  $promotions
     * @return array{discount: int, applied: Collection<int, array{promotion: Promotion, discount: int}>}
     */
    private function calculateDiscounts(Collection $promotions, int $subtotal): array
    {
        if ($promotions->isEmpty()) {
            return [
                'discount' => 0,
                'applied' => collect(),
            ];
        }

        $appliedPromotions = collect();
        $totalDiscount = 0;
        $remainingAmount = $subtotal;
        $hasAppliedNonStackable = false;

        foreach ($promotions as $promotion) {
            if ($hasAppliedNonStackable && ! $promotion->is_stackable) {
                continue;
            }

            if (! $hasAppliedNonStackable || $promotion->is_stackable) {
                $discount = $promotion->calculateDiscount($remainingAmount);

                if ($discount > 0) {
                    $totalDiscount += $discount;
                    $remainingAmount -= $discount;
                    $appliedPromotions->push([
                        'promotion' => $promotion,
                        'discount' => $discount,
                    ]);

                    if (! $promotion->is_stackable) {
                        $hasAppliedNonStackable = true;
                    }
                }
            }
        }

        return [
            'discount' => min($totalDiscount, $subtotal),
            'applied' => $appliedPromotions,
        ];
    }

    /**
     * @param  Collection<int, array{promotion: Promotion, discount: int}>  $promotions
     * @return array<int, array<string, mixed>>
     */
    private function formatAppliedPromotions(Collection $promotions): array
    {
        $applied = [];

        foreach ($promotions as $appliedPromotion) {
            $promotion = $appliedPromotion['promotion'];

            $applied[] = [
                'promotion_id' => $promotion->id,
                'name' => $promotion->name,
                'code' => $promotion->code ?? null,
                'type' => $promotion->type->value,
                'discount' => $appliedPromotion['discount'],
            ];
        }

        return $applied;
    }

    private function resolveCodePromotion(CheckoutSession $session, TargetingContext $context): ?Promotion
    {
        $promoCode = $this->resolvePromoCode($session);

        if ($promoCode === null || $this->promoCodeResolvesToVoucher($promoCode, $session)) {
            return null;
        }

        /** @var Collection<int, Promotion> $promotions */
        $promotions = Promotion::query()
            ->active()
            ->withCode()
            ->forOwner()
            ->whereRaw('LOWER(code) = LOWER(?)', [$promoCode])
            ->orderByDesc('priority')
            ->get();

        return $promotions->first(fn (Promotion $promotion): bool => $this->matchesContext($promotion, $context));
    }

    private function promoCodeResolvesToVoucher(string $promoCode, CheckoutSession $session): bool
    {
        if (! interface_exists(VoucherServiceInterface::class)) {
            return false;
        }

        $voucherService = app(VoucherServiceInterface::class);

        $validation = $voucherService->validate($promoCode, [
            'customer_id' => $session->customer_id,
            'subtotal' => $session->subtotal,
            'currency' => $session->currency,
        ]);

        if ($validation instanceof VoucherValidationResult) {
            return $validation->isValid;
        }

        return (bool) ($validation['valid'] ?? false);
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

    private function matchesContext(Promotion $promotion, TargetingContext $context): bool
    {
        if (! $promotion->isActive()) {
            return false;
        }

        $conditions = $promotion->conditions;

        if ($conditions === null || $conditions === []) {
            return true;
        }

        if (! is_array($conditions) || $this->targetingEngine === null) {
            return false;
        }

        return $this->targetingEngine->evaluate($conditions, $context);
    }
}
