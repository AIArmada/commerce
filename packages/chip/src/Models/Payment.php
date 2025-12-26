<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $purchase_id
 * @property string|null $type
 * @property string|null $status
 * @property int $amount
 * @property string $currency
 * @property int $net_amount
 * @property int $fee_amount
 * @property int $pending_amount
 * @property bool $is_outgoing
 * @property int|null $paid_on
 * @property int|null $remote_paid_on
 * @property int|null $created_on
 * @property int|null $updated_on
 * @property int|null $pending_unfreeze_on
 * @property-read Money|null $amountMoney
 * @property-read Money|null $netAmountMoney
 * @property-read Money|null $feeAmountMoney
 * @property-read Money|null $pendingAmountMoney
 */
class Payment extends ChipModel
{
    public function paidOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?Carbon => $this->toTimestamp($attributes['paid_on'] ?? null));
    }

    public function remotePaidOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?Carbon => $this->toTimestamp($attributes['remote_paid_on'] ?? null));
    }

    public function createdOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?Carbon => $this->toTimestamp($attributes['created_on'] ?? null));
    }

    public function updatedOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?Carbon => $this->toTimestamp($attributes['updated_on'] ?? null));
    }

    public function amountMoney(): Attribute
    {
        return Attribute::get(fn (): ?Money => $this->toMoney($this->amount, $this->currency));
    }

    public function netAmountMoney(): Attribute
    {
        return Attribute::get(fn (): ?Money => $this->toMoney($this->net_amount, $this->currency));
    }

    public function feeAmountMoney(): Attribute
    {
        return Attribute::get(fn (): ?Money => $this->toMoney($this->fee_amount, $this->currency));
    }

    public function pendingAmountMoney(): Attribute
    {
        return Attribute::get(fn (): ?Money => $this->toMoney($this->pending_amount, $this->currency));
    }

    public function formattedAmount(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->amountMoney?->format());
    }

    public function formattedNetAmount(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->netAmountMoney?->format());
    }

    public function formattedFeeAmount(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->feeAmountMoney?->format());
    }

    public function formattedPendingAmount(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->pendingAmountMoney?->format());
    }

    public function pendingUnfreezeOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?Carbon => $this->toTimestamp($attributes['pending_unfreeze_on'] ?? null));
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    protected static function tableSuffix(): string
    {
        return 'payments';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'is_outgoing' => 'boolean',
            'pending_unfreeze_on' => 'integer',
            'paid_on' => 'integer',
            'remote_paid_on' => 'integer',
        ];
    }
}
