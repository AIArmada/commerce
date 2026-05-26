<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Models;

use AIArmada\FilamentPromotions\Database\Factories\PromotionFactory;
use AIArmada\FilamentPromotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion as BasePromotion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Throwable;

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
     * @return HasMany<Model, $this>
     */
    public function issuedVouchers(): HasMany
    {
        $voucherModelClass = self::issuedVoucherModelClass();

        if ($voucherModelClass === null) {
            return $this->hasMany(self::class, 'id', 'id')->whereRaw('1 = 0');
        }

        return $this->hasMany($voucherModelClass, 'promotion_id');
    }

    public static function supportsIssuedVoucherTracking(): bool
    {
        $voucherModelClass = self::issuedVoucherModelClass();

        if ($voucherModelClass === null) {
            return false;
        }

        try {
            /** @var Model $voucher */
            $voucher = new $voucherModelClass;
            $table = $voucher->getTable();

            return Schema::hasTable($table) && Schema::hasColumn($table, 'promotion_id');
        } catch (Throwable) {
            return false;
        }
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

    /**
     * @return class-string<Model>|null
     */
    private static function issuedVoucherModelClass(): ?string
    {
        foreach ([
            '\\AIArmada\\FilamentVouchers\\Models\\Voucher',
            '\\AIArmada\\Vouchers\\Models\\Voucher',
        ] as $voucherModelClass) {
            if (class_exists($voucherModelClass)) {
                /** @var class-string<Model> $voucherModelClass */

                return $voucherModelClass;
            }
        }

        return null;
    }
}
