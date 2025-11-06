<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property int $amount
 * @property string $currency
 * @property int $net_amount
 * @property int $fee
 * @property string $status
 */
final class ChipSendLimit extends ChipModel
{
    public $timestamps = false;

    public function formattedAmount(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->formatMoney((int) $this->amount, $this->currency));
    }

    public function formattedNetAmount(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->formatMoney((int) $this->net_amount, $this->currency));
    }

    public function formattedFee(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->formatMoney((int) $this->fee, $this->currency));
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'active', 'approved' => 'success',
            'pending', 'review' => 'warning',
            'expired', 'rejected', 'blocked' => 'danger',
            default => 'gray',
        };
    }

    protected static function tableSuffix(): string
    {
        return 'send_limits';
    }

    protected function casts(): array
    {
        return [
            'from_settlement' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
