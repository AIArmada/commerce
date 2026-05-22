<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

final class RelatedObjectData extends ChipData
{
    public function __construct(
        public readonly ?string $type,
        public readonly ?string $id,
    ) {}

    public static function from(mixed ...$payloads): static
    {
        $data = self::resolvePayload(...$payloads);

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
