<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

final readonly class CurrencyConversionData
{
    public function __construct(
        public string $original_currency,
        public int $original_amount,
        public float $exchange_rate,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        return new self(
            original_currency: (string) $data['original_currency'],
            original_amount: (int) $data['original_amount'],
            exchange_rate: (float) $data['exchange_rate'],
        );
    }

    public function getOriginalAmountInCurrency(): float
    {
        return $this->original_amount / 100;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'original_currency' => $this->original_currency,
            'original_amount' => $this->original_amount,
            'exchange_rate' => $this->exchange_rate,
        ];
    }
}
