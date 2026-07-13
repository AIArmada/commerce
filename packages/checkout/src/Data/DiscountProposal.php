<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Data;

final class DiscountProposal
{
    public function __construct(
        public readonly string $providerKey,
        public readonly string $candidateKey,
        public readonly int $requestedAmount,
        public readonly ?string $label = null,
        public readonly ?string $code = null,
        public readonly int $priority = 0,
        /** @var array<string, mixed> */
        public readonly array $meta = [],
    ) {}

    public function withAllocatedAmount(int $amount): self
    {
        return new self(
            providerKey: $this->providerKey,
            candidateKey: $this->candidateKey,
            requestedAmount: $amount,
            label: $this->label,
            code: $this->code,
            priority: $this->priority,
            meta: $this->meta,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_key' => $this->providerKey,
            'candidate_key' => $this->candidateKey,
            'requested_amount' => $this->requestedAmount,
            'label' => $this->label,
            'code' => $this->code,
            'priority' => $this->priority,
            'meta' => $this->meta,
        ];
    }
}
