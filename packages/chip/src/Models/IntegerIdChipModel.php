<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * Base model for CHIP entities that use integer primary keys from external API.
 *
 * These models represent entities where the ID is assigned by the CHIP API
 * (Send API entities like bank accounts, send instructions, etc.) and cannot
 * use Laravel's UUID generation.
 */
abstract class IntegerIdChipModel extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'int';

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

    protected function formatMoney(?int $amount, ?string $currency, int $divideBy = 100): ?string
    {
        if ($amount === null) {
            return null;
        }

        $precision = (int) config('chip.database.amount_precision', 2);
        $value = $divideBy > 0 ? $amount / $divideBy : $amount;
        $formatted = number_format($value, $precision, '.', ',');

        return mb_trim(sprintf('%s %s', $currency ?? '', $formatted));
    }
}
