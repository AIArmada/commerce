<?php

declare(strict_types=1);

namespace AIArmada\Chip\DataObjects;

final class TransactionData
{
    public function __construct(
        public readonly ?string $payment_method,
        /** @var array<string, mixed> */
        public readonly array $extra,
        public readonly ?string $country,
        /** @var array<string, mixed> */
        public readonly array $attempts,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            payment_method: $data['payment_method'] ?? null,
            extra: $data['extra'] ?? [],
            country: $data['country'] ?? null,
            attempts: $data['attempts'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    /**
     * @return array<string, mixed>|null
     */
    public function getLastAttempt(): ?array
    {
        if (! empty($this->attempts)) {
            $attempts = $this->attempts;
            $first = reset($attempts);

            return is_array($first) ? $first : null;
        }

        return null;
    }

    public function hasFailedAttempts(): bool
    {
        return ! empty(array_filter($this->attempts, fn ($attempt) => ! ($attempt['successful'] ?? true)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFailedAttempts(): array
    {
        return array_values(array_filter($this->attempts, fn ($attempt) => is_array($attempt) && ! ($attempt['successful'] ?? true)));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'payment_method' => $this->payment_method,
            'extra' => $this->extra,
            'country' => $this->country,
            'attempts' => $this->attempts,
        ];
    }
}
