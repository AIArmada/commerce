<?php

declare(strict_types=1);

namespace AIArmada\Chip\DataObjects;

use Carbon\Carbon;

final class SendLimit
{
    public function __construct(
        public readonly int $id,
        public readonly string $currency,
        public readonly string $fee_type,
        public readonly string $transaction_type,
        public readonly int $amount,
        public readonly int $fee,
        public readonly int $net_amount,
        public readonly string $status,
        public readonly int $approvals_required,
        public readonly int $approvals_received,
        public readonly ?string $from_settlement,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            currency: $data['currency'],
            fee_type: $data['fee_type'],
            transaction_type: $data['transaction_type'],
            amount: (int) ($data['amount'] ?? 0),
            fee: (int) ($data['fee'] ?? 0),
            net_amount: (int) ($data['net_amount'] ?? 0),
            status: $data['status'] ?? 'unknown',
            approvals_required: (int) ($data['approvals_required'] ?? 0),
            approvals_received: (int) ($data['approvals_received'] ?? 0),
            from_settlement: $data['from_settlement'] ?? null,
            created_at: (string) ($data['created_at'] ?? Carbon::now()->toISOString()),
            updated_at: (string) ($data['updated_at'] ?? Carbon::now()->toISOString()),
        );
    }

    public function getAmountInMajorUnits(): float
    {
        return $this->amount / 100;
    }

    public function getFeeInMajorUnits(): float
    {
        return $this->fee / 100;
    }

    public function getNetAmountInMajorUnits(): float
    {
        return $this->net_amount / 100;
    }

    public function getCreatedAt(): Carbon
    {
        return $this->parseTimestamp($this->created_at);
    }

    public function getUpdatedAt(): Carbon
    {
        return $this->parseTimestamp($this->updated_at);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'currency' => $this->currency,
            'fee_type' => $this->fee_type,
            'transaction_type' => $this->transaction_type,
            'amount' => $this->amount,
            'fee' => $this->fee,
            'net_amount' => $this->net_amount,
            'status' => $this->status,
            'approvals_required' => $this->approvals_required,
            'approvals_received' => $this->approvals_received,
            'from_settlement' => $this->from_settlement,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function parseTimestamp(string $value): Carbon
    {
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        return Carbon::parse($value);
    }
}
