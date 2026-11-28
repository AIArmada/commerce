<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Data;

final readonly class ContactSnapshotSourceData
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $id,
        public string $type,
        public string $snapshotType,
        public ?string $ownerType,
        public ?string $ownerId,
        public ?string $label,
        public string $channel,
        public ?string $value,
        public ?string $normalizedValue,
        public ?string $url,
        public ?string $displayValue,
        public bool $isPublic,
        public array $payload,
    ) {}
}
