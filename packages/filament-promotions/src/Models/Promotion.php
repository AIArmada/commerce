<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Models;

use AIArmada\FilamentPromotions\Database\Factories\PromotionFactory;
use AIArmada\FilamentPromotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion as BasePromotion;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Filament-aware Promotion model that uses the Filament PromotionType enum.
 *
 * @property PromotionType $type
 */
class Promotion extends BasePromotion
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    protected static function newFactory(): PromotionFactory
    {
        return PromotionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'type' => PromotionType::class,
        ]);
    }
}
