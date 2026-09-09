<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Promotions\Contracts\PromotionServiceInterface;
use AIArmada\Promotions\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class ApplyPromotionalAdjustment
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{price: int, name: string}|null
     */
    public function apply(
        string $promotionableType,
        string $promotionableId,
        int $basePrice,
        int $quantity,
        CarbonImmutable $effectiveAt,
        array $context = [],
    ): ?array {
        if (! interface_exists(PromotionServiceInterface::class) || ! app()->bound(PromotionServiceInterface::class)) {
            return null;
        }

        if ($quantity <= 0) {
            return null;
        }

        $targetingContext = $this->makeTargetingContext(
            $promotionableType,
            $promotionableId,
            $basePrice,
            $quantity,
            $effectiveAt,
            $context,
        );

        /** @var PromotionServiceInterface $promotionService */
        $promotionService = app(PromotionServiceInterface::class);
        $discountResult = $promotionService->calculateDiscounts($targetingContext, $basePrice * $quantity);

        /** @var Collection<int, Promotion> $appliedPromotions */
        $appliedPromotions = $discountResult['applied'];
        $promotion = $appliedPromotions->first();

        if ($promotion === null) {
            return null;
        }

        $finalPrice = max(0, intdiv(($basePrice * $quantity) - $discountResult['discount'], $quantity));

        return [
            'price' => $finalPrice,
            'name' => $promotion->name,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function makeTargetingContext(
        string $promotionableType,
        string $promotionableId,
        int $basePrice,
        int $quantity,
        CarbonImmutable $effectiveAt,
        array $context,
    ): TargetingContext {
        $item = new class($promotionableId, $quantity)
        {
            public function __construct(
                public string $id,
                public int $quantity,
            ) {}

            public function getAttribute(string $key): mixed
            {
                return null;
            }
        };

        $cart = new class($item, $basePrice * $quantity)
        {
            public function __construct(
                private readonly object $item,
                private readonly int $subtotal,
            ) {}

            public function getSubtotal(): int
            {
                return $this->subtotal;
            }

            /**
             * @return Collection<int, object>
             */
            public function getItems(): Collection
            {
                return collect([$this->item]);
            }
        };

        return new TargetingContext(
            cart: $cart,
            metadata: array_merge($context, [
                'promotionable_type' => $promotionableType,
                'effective_at' => $effectiveAt,
                'currency' => $context['currency'] ?? config('pricing.defaults.currency', 'MYR'),
            ]),
        );
    }
}
