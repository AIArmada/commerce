<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

abstract class ChipModel extends Model
{
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

    protected function formatMoney(?int $amount, ?string $currency, int $divideBy = 100): ?string
    {
        if ($amount === null) {
            return null;
        }

        $precision = (int) config('filament-chip.tables.amount_precision', 2);
        $value = $divideBy > 0 ? $amount / $divideBy : $amount;
        $formatted = number_format($value, $precision, '.', ',');

        return mb_trim(sprintf('%s %s', $currency ?? '', $formatted));
    }
}
