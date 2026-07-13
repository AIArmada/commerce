<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Data;

final class DiscountCommitment
{
    public function __construct(
        public readonly string $providerKey,
        public readonly string $candidateKey,
        public readonly int $appliedAmount,
        public readonly string $reservationToken,
        /** @var array<string, mixed> */
        public readonly array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_key' => $this->providerKey,
            'candidate_key' => $this->candidateKey,
            'applied_amount' => $this->appliedAmount,
            'reservation_token' => $this->reservationToken,
            'meta' => $this->meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            providerKey: (string) ($data['provider_key'] ?? ''),
            candidateKey: (string) ($data['candidate_key'] ?? ''),
            appliedAmount: (int) ($data['applied_amount'] ?? 0),
            reservationToken: (string) ($data['reservation_token'] ?? ''),
            meta: (array) ($data['meta'] ?? []),
        );
    }
}
