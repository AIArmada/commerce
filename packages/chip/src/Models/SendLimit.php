<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property int $id
 * @property string $amount Amount in major currency units
 * @property string $fee Amount in major currency units
 * @property string $net_amount Amount in major currency units
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
        return Attribute::get(fn (): ?Money => $this->toMoney(
            $this->convertMajorAmountToMinorUnits((string) $this->amount, (string) $this->currency),
            (string) $this->currency,
        ));
    }

    public function netAmountMoney(): Attribute
    {
        return Attribute::get(fn (): ?Money => $this->toMoney(
            $this->convertMajorAmountToMinorUnits((string) $this->net_amount, (string) $this->currency),
            (string) $this->currency,
        ));
    }

    public function feeMoney(): Attribute
    {
        return Attribute::get(fn (): ?Money => $this->toMoney(
            $this->convertMajorAmountToMinorUnits((string) $this->fee, (string) $this->currency),
            (string) $this->currency,
        ));
    }

    public function statusColor(): string
    {
        $status = $this->status ?? '';

        return match ($status) {
            'approved', 'success' => 'success',
            'pending' => 'warning',
            'expired' => 'danger',
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
