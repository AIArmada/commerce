<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

final readonly class TransactionData
{
    public function __construct(
        public ?string $payment_method,
        /** @var array<string, mixed> */
        public array $extra,
        public ?string $country,
        /** @var array<string, mixed> */
        public array $attempts,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
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
    public function getLastAttempt(): ?array
    {
        if ($this->attempts === []) {
            return null;
        }

        $attempt = $this->attempts[0] ?? null;

        return is_array($attempt) ? $attempt : null;
    }

    public function hasFailedAttempts(): bool
    {
        return ! empty(array_filter(
            $this->attempts,
            fn (mixed $attempt): bool => is_array($attempt) && ($attempt['successful'] ?? true) === false,
        ));
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
