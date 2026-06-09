<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\Promotions\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class ApplyPromotionalAdjustment
{
    /**
     * @return array{price: int, name: string}|null
     */
    public function apply(string $promotionableType, string $promotionableId, int $basePrice, int $quantity, CarbonImmutable $effectiveAt): ?array
    {
        $promotionClass = '\\AIArmada\\Promotions\\Models\\Promotion';

        if (! class_exists($promotionClass)) {
            return null;
        }

        /** @var Promotion $promotionModel */
        $promotionModel = new $promotionClass;
        $promotionTable = $promotionModel->getTable();
        $promotionablesTable = (string) config('promotions.database.tables.promotionables', 'promotionables');

        /** @var Promotion|null $promotion */
        $promotion = $this->applyPromotionActiveAt($promotionClass::query(), $effectiveAt)
            ->whereExists(function ($query) use ($promotionTable, $promotionablesTable, $promotionableType, $promotionableId): void {
                $query->selectRaw('1')
                    ->from($promotionablesTable)
                    ->whereColumn("{$promotionablesTable}.promotion_id", "{$promotionTable}.id")
                    ->where("{$promotionablesTable}.promotionable_type", $promotionableType)
                    ->where("{$promotionablesTable}.promotionable_id", $promotionableId);
            })
            ->orderBy('priority', 'desc')
            ->first();

        if (! $promotion) {
            return null;
        }

        if ($promotion->min_quantity !== null && $quantity < $promotion->min_quantity) {
            return null;
        }

        if ($promotion->min_purchase_amount !== null && ($basePrice * $quantity) < $promotion->min_purchase_amount) {
            return null;
        }

        $discount = $promotion->calculateDiscount($basePrice);
        $finalPrice = max(0, $basePrice - $discount);

        return [
            'price' => $finalPrice,
            'name' => $promotion->name,
        ];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function applyPromotionActiveAt(Builder $query, CarbonImmutable $at): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) use ($at): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function ($q) use ($at): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            })
            ->where(function ($q): void {
                $q->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            });
    }
}
