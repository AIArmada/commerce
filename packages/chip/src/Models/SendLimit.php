<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property int $amount
 * @property string|null $currency
 * @property int $net_amount
 * @property int $fee
 * @property string|null $status
 */
class SendLimit extends ChipModel
{
    public $timestamps = false;

    /** @return Attribute<string|null, never> */
    public function formattedAmount(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->formatMoney((int) $this->amount, $this->currency));
    }

    /** @return Attribute<string|null, never> */
    public function formattedNetAmount(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->formatMoney((int) $this->net_amount, $this->currency));
    }

    /** @return Attribute<string|null, never> */
    public function formattedFee(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->formatMoney((int) $this->fee, $this->currency));
    }

    public function statusColor(): string
    {
        $status = $this->status ?? '';

        return match ($status) {
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_settlement' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
