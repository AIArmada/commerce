<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class SendLimitData extends ChipData
{
    public function __construct(
        public readonly int $id,
        public readonly string $currency,
        public readonly string $fee_type,
        public readonly string $transaction_type,
        public readonly int | float $amount,
        public readonly int | float $fee,
        public readonly int | float $net_amount,
        public readonly string $status,
        public readonly int $approvals_required,
        public readonly int $approvals_received,
        public readonly ?string $from_settlement,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}

    public static function from(mixed ...$payloads): static
    {
        $data = self::resolvePayload(...$payloads);

        return new self(
            id: (int) $data['id'],
            currency: (string) $data['currency'],
            fee_type: (string) $data['fee_type'],
            transaction_type: (string) $data['transaction_type'],
            amount: self::numericValue($data['amount']),
            fee: self::numericValue($data['fee']),
            net_amount: self::numericValue($data['net_amount']),
            status: (string) $data['status'],
            approvals_required: (int) $data['approvals_required'],
            approvals_received: (int) $data['approvals_received'],
            from_settlement: isset($data['from_settlement'])
                ? (string) $data['from_settlement']
                : null,
            created_at: (string) $data['created_at'],
            updated_at: (string) $data['updated_at'],
        );
    }

    public function getCreatedAt(): CarbonImmutable
    {
        return $this->parseTimestamp($this->created_at);
    }

    public function getUpdatedAt(): CarbonImmutable
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

    private function parseTimestamp(string $value): CarbonImmutable
    {
        if (is_numeric($value)) {
            return CarbonImmutable::createFromTimestamp((int) $value);
        }

        return CarbonImmutable::parse($value);
    }

    private static function numericValue(mixed $value): int | float
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            throw new InvalidArgumentException('CHIP Send limit monetary values must be numeric.');
        }

        $value = (string) $value;

        return str_contains($value, '.') ? (float) $value : (int) $value;
    }
}
