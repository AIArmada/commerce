<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

final class CurrencyConversionData extends ChipData
{
    public function __construct(
        public readonly string $original_currency,
        public readonly int $original_amount,
        public readonly float $exchange_rate,
    ) {}

    public static function from(mixed ...$payloads): static
    {
        $data = self::resolvePayload(...$payloads);

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
