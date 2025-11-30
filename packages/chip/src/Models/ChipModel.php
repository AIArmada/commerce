<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

abstract class ChipModel extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    abstract protected static function tableSuffix(): string;

    #[Override]
    final public function getTable(): string
    {
        $prefix = (string) config('chip.database.table_prefix', 'chip_');

        return $prefix.static::tableSuffix();
    }

    protected function toTimestamp(?int $value): ?Carbon
    {
        return $value !== null ? Carbon::createFromTimestampUTC($value) : null;
    }

    /**
     * Convert an amount in cents to a Money object.
     *
     * @param int|null $amount Amount in cents (smallest currency unit)
     * @param string $currency ISO 4217 currency code (default: MYR)
     */
    protected function toMoney(?int $amount, string $currency = 'MYR'): ?Money
    {
        if ($amount === null) {
            return null;
        }

        return Money::{$currency}($amount);
    }
}
