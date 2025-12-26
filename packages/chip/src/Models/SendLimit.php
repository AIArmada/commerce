<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property int $id
 * @property int $amount
 * @property int $fee
 * @property int $net_amount
 * @property string $currency
 * @property string $fee_type
 * @property string $transaction_type
 * @property string|null $status
 * @property int $approvals_required
 * @property int $approvals_received
 */
class SendLimit extends ChipIntegerModel
{
    public function amountMoney(): Attribute
    {
        return Attribute::get(fn (): ?Money => $this->toMoney((int) $this->amount, $this->currency));
    }

    public function netAmountMoney(): Attribute
    {
        return Attribute::get(fn (): ?Money => $this->toMoney((int) $this->net_amount, $this->currency));
    }

    public function feeMoney(): Attribute
    {
        return Attribute::get(fn (): ?Money => $this->toMoney((int) $this->fee, $this->currency));
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
