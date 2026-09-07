<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use AIArmada\Chip\Models\Concerns\AutoAssignOwnerOnCreate;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Akaunting\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Override;

/**
 * Base model for CHIP tables that use integer primary keys.
 *
 * Used by: BankAccountData, SendInstruction, SendLimit
 * These tables mirror the CHIP Send API which uses integer IDs.
 */
abstract class ChipIntegerModel extends Model
{
    use AutoAssignOwnerOnCreate;
    use HasOwner {
        scopeForOwner as private scopeForOwnerUsingTrait;
    }
    use HasOwnerScopeConfig;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'chip.owner';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [
        'owner_type',
        'owner_id',
    ];

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

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    final public function scopeForOwner(Builder $query, Model | string | null $owner = OwnerContext::CURRENT, ?bool $includeGlobal = null): Builder
    {
        if (! (bool) config('chip.owner.enabled', false)) {
            return $query;
        }

        if ($owner === OwnerContext::CURRENT) {
            $owner = $this->resolveOwner();

            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                sprintf('%s requires an owner context or explicit global context.', static::class),
            );
        }

        $includeGlobal ??= (bool) config('chip.owner.include_global', false);

        return $this->scopeForOwnerUsingTrait($query, $owner, $includeGlobal);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    final public function scopeForOwnerIncludingGlobal(Builder $query, Model | string | null $owner = OwnerContext::CURRENT): Builder
    {
        return $this->scopeForOwner($query, $owner, true);
    }

    protected function resolveOwner(): ?Model
    {
        return OwnerContext::resolve();
    }

    protected function toTimestamp(?int $value): ?CarbonImmutable
    {
        return $value !== null ? CarbonImmutable::createFromTimestampUTC($value) : null;
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

    /**
     * Convert CHIP Send's major-unit decimal boundary to integer minor units.
     * CHIP returns these values as decimal strings or numeric values. Half-up
     * rounding is explicit because the package stores and exposes minor units.
     */
    protected function convertMajorAmountToMinorUnits(string $amount, string $currency): int
    {
        $normalized = mb_trim($amount);

        if ($normalized === '') {
            return 0;
        }

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException('CHIP Send monetary values must be numeric.');
        }

        $major = (float) $normalized;
        if (! is_finite($major)) {
            throw new InvalidArgumentException('CHIP Send monetary values must be finite.');
        }

        $scale = 10 ** MoneyFormatter::precisionFor($currency);

        return (int) round($major * $scale, 0, PHP_ROUND_HALF_UP);
    }
}
