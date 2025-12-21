<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property int $id
 * @property int $amount Amount in cents (smallest currency unit)
 * @property int $fee Fee amount in cents
 * @property int $net_amount Net amount in cents after fees
 * @property string $currency
 * @property string $fee_type
 * @property string $transaction_type
 * @property string $status
 * @property int $approvals_required
 * @property int $approvals_received
 * @property string|null $from_settlement
 */
class SendLimit extends IntegerIdChipModel
{
    public $timestamps = true;

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
