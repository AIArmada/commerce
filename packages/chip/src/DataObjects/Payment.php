<?php

declare(strict_types=1);

namespace AIArmada\Chip\DataObjects;

use Carbon\Carbon;

final class Payment
{
    public function __construct(
        public readonly bool $is_outgoing,
        public readonly string $payment_type,
        public readonly int $amount,
        public readonly string $currency,
        public readonly int $net_amount,
        public readonly int $fee_amount,
        public readonly int $pending_amount,
        public readonly ?int $pending_unfreeze_on,
        public readonly ?string $description,
        public readonly ?int $paid_on,
        public readonly ?int $remote_paid_on,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            is_outgoing: $data['is_outgoing'] ?? false,
            payment_type: $data['payment_type'] ?? 'purchase',
            amount: (int) ($data['amount'] ?? 0),
            currency: $data['currency'] ?? 'MYR',
            net_amount: (int) ($data['net_amount'] ?? 0),
            fee_amount: (int) ($data['fee_amount'] ?? 0),
            pending_amount: (int) ($data['pending_amount'] ?? 0),
            pending_unfreeze_on: isset($data['pending_unfreeze_on']) ? (int) $data['pending_unfreeze_on'] : null,
            description: $data['description'] ?? null,
            paid_on: isset($data['paid_on']) ? (int) $data['paid_on'] : null,
            remote_paid_on: isset($data['remote_paid_on']) ? (int) $data['remote_paid_on'] : null,
        );
    }

    public function getPaidAt(): ?Carbon
    {
        return $this->paid_on ? Carbon::createFromTimestamp($this->paid_on) : null;
    }

    public function getRemotePaidAt(): ?Carbon
    {
        return $this->remote_paid_on ? Carbon::createFromTimestamp($this->remote_paid_on) : null;
    }

    public function getPendingUnfreezeAt(): ?Carbon
    {
        return $this->pending_unfreeze_on ? Carbon::createFromTimestamp($this->pending_unfreeze_on) : null;
    }

    public function getAmountInMajorUnits(): float
    {
        return $this->amount / 100;
    }

    public function getNetAmountInMajorUnits(): float
    {
        return $this->net_amount / 100;
    }

    public function getFeeAmountInMajorUnits(): float
    {
        return $this->fee_amount / 100;
    }

    public function isPaid(): bool
    {
        return $this->paid_on !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_outgoing' => $this->is_outgoing,
            'payment_type' => $this->payment_type,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'net_amount' => $this->net_amount,
            'fee_amount' => $this->fee_amount,
            'pending_amount' => $this->pending_amount,
            'pending_unfreeze_on' => $this->pending_unfreeze_on,
            'description' => $this->description,
            'paid_on' => $this->paid_on,
            'remote_paid_on' => $this->remote_paid_on,
        ];
    }
}
