<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

final readonly class EventTermData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public string $taxonomyId,
        public ?string $parentId,
        public string $code,
        public string $name,
        public ?string $description,
        public int $sortOrder,
        public bool $isActive,
        public ?array $metadata = null,
    ) {}
}
