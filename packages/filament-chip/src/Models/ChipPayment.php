<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $amount
 * @property string $currency
 * @property int $net_amount
 * @property int $fee_amount
 * @property int $pending_amount
 */
final class ChipPayment extends ChipModel
{
    public $incrementing = false;

    protected $keyType = 'string';

    public function paidOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?\Illuminate\Support\Carbon => $this->toTimestamp($attributes['paid_on'] ?? null));
    }

    public function remotePaidOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?\Illuminate\Support\Carbon => $this->toTimestamp($attributes['remote_paid_on'] ?? null));
    }

    public function createdOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?\Illuminate\Support\Carbon => $this->toTimestamp($attributes['created_on'] ?? null));
    }

    public function updatedOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?\Illuminate\Support\Carbon => $this->toTimestamp($attributes['updated_on'] ?? null));
    }

    public function formattedAmount(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->formatMoney($this->amount, $this->currency));
    }

    public function formattedNetAmount(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->formatMoney($this->net_amount, $this->currency));
    }

    public function formattedFeeAmount(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->formatMoney($this->fee_amount, $this->currency));
    }

    public function formattedPendingAmount(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->formatMoney($this->pending_amount, $this->currency));
    }

    public function pendingUnfreezeOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?\Illuminate\Support\Carbon => $this->toTimestamp($attributes['pending_unfreeze_on'] ?? null));
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(ChipPurchase::class, 'purchase_id');
    }

    protected static function tableSuffix(): string
    {
        return 'payments';
    }

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
