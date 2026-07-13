<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations;

use AIArmada\Checkout\Contracts\DiscountProvider;
use AIArmada\Checkout\Data\DiscountCommitment;
use AIArmada\Checkout\Data\DiscountProposal;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Promotions\Contracts\PromotionServiceInterface;
use AIArmada\Promotions\Models\Promotion;
use Illuminate\Support\Collection;

final class PromotionsAdapter implements DiscountProvider
{
    public function __construct(
        private readonly ?DiscountCodeResolver $discountCodeResolver = null,
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
        $resolvedDiscountCode = $this->discountCodeResolver()->resolve($session, $context);
        $codePromotion = $resolvedDiscountCode->promotion;

        if ($resolvedDiscountCode->isPromotion() && $codePromotion instanceof Promotion) {
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

    private function discountCodeResolver(): DiscountCodeResolver
    {
        return $this->discountCodeResolver ?? app(DiscountCodeResolver::class);
    }

    public function providerKey(): string
    {
        return 'promotions';
    }

    public function evaluate(CheckoutSession $session, array $discountData): array
    {
        $result = $this->applyEligiblePromotions($session);
        $proposals = [];

        foreach ($result['applied'] as $applied) {
            $proposals[] = new DiscountProposal(
                providerKey: 'promotions',
                candidateKey: 'promotion:' . ($applied['id'] ?? $applied['promotion_id'] ?? ''),
                requestedAmount: $applied['discount'] ?? 0,
                label: $applied['name'] ?? null,
                code: $applied['code'] ?? null,
                priority: 60,
                meta: [
                    'promotion_id' => $applied['id'] ?? $applied['promotion_id'] ?? null,
                    'promotion_code' => $applied['code'] ?? null,
                ],
            );
        }

        return $proposals;
    }

    public function commit(CheckoutSession $session, array $accepted): array
    {
        $commitments = [];
        foreach ($accepted as $proposal) {
            $key = $proposal->providerKey . ':' . $proposal->candidateKey;
            $commitments[$key] = new DiscountCommitment(
                providerKey: 'promotions',
                candidateKey: $proposal->candidateKey,
                appliedAmount: $proposal->requestedAmount,
                reservationToken: $proposal->candidateKey,
                meta: $proposal->meta,
            );
        }

        return $commitments;
    }

    public function release(CheckoutSession $session, array $commitments): void
    {
        // ponytail: promotions have no reservation lifecycle in Wave 1 — noop
    }
}
