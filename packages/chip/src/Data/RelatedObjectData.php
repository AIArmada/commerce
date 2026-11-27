<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

final readonly class RelatedObjectData
{
    public function __construct(
        public ?string $type,
        public ?string $id,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        return new self(
            type: $data['type'] ?? null,
            id: $data['id'] ?? null,
        );
    }

    public function isPurchase(): bool
    {
        return $this->type === 'purchase';
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'id' => $this->id,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
