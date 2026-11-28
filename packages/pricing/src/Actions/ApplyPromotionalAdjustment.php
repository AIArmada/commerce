<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Promotions\Contracts\PromotionServiceInterface;
use AIArmada\Promotions\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use InvalidArgumentException;

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

        if ($basePrice < 0 || $basePrice > intdiv(PHP_INT_MAX, $quantity)) {
            throw new InvalidArgumentException('Promotion subtotal exceeds the supported monetary range.');
        }

        $subtotal = $basePrice * $quantity;
        $targetingContext = $this->makeTargetingContext(
            $promotionableType,
            $promotionableId,
            $subtotal,
            $quantity,
            $effectiveAt,
            $context,
        );

        /** @var PromotionServiceInterface $promotionService */
        $promotionService = app(PromotionServiceInterface::class);
        $discountResult = $promotionService->calculateDiscounts($targetingContext, $subtotal);

        /** @var Collection<int, Promotion> $appliedPromotions */
        $appliedPromotions = $discountResult['applied'];
        $promotion = $appliedPromotions->first();

        if ($promotion === null) {
            return null;
        }

        $finalPrice = max(0, intdiv($subtotal - $discountResult['discount'], $quantity));

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
        int $subtotal,
        int $quantity,
        CarbonImmutable $effectiveAt,
        array $context,
    ): TargetingContext {
        $priceable = $this->resolvePriceable($promotionableType, $promotionableId);

        $item = new class($promotionableId, $quantity, $priceable)
        {
            public function __construct(
                public string $id,
                public int $quantity,
                public readonly ?Model $associatedModel = null,
            ) {}

            public function getAttribute(string $key): mixed
            {
                return $this->associatedModel?->getAttribute($key);
            }
        };

        $cart = new class($item, $subtotal)
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

    private function resolvePriceable(string $type, string $id): ?Model
    {
        $type = Relation::getMorphedModel($type) ?? $type;

        if (! is_a($type, Model::class, true)) {
            return null;
        }

        return $type::query()->whereKey($id)->first();
    }
}
