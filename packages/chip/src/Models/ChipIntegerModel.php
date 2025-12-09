<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * Base model for CHIP tables that use integer primary keys.
 *
 * Used by: BankAccountData, SendInstruction, SendLimit, SendWebhook
 * These tables mirror the CHIP Send API which uses integer IDs.
 */
abstract class ChipIntegerModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * The primary key type.
     *
     * @var string
     */
    protected $keyType = 'int';

    abstract protected static function tableSuffix(): string;

    #[Override]
    final public function getTable(): string
    {
        $prefix = (string) config('chip.database.table_prefix', 'chip_');

        return $prefix . static::tableSuffix();
    }

    protected function toTimestamp(?int $value): ?Carbon
    {
        return $value !== null ? Carbon::createFromTimestampUTC($value) : null;
    }

    /**
     * Convert an amount in cents to a Money object.
     *
     * @param  int|null  $amount  Amount in cents (smallest currency unit)
     * @param  string  $currency  ISO 4217 currency code (default: MYR)
     */
    protected function toMoney(?int $amount, string $currency = 'MYR'): ?Money
    {
        if ($amount === null) {
            return null;
        }

        return Money::{$currency}($amount);
    }
}
