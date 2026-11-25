<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

final readonly class EventTaxonomyData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public ?string $description,
        public bool $isHierarchical,
        public bool $isActive,
        public ?array $metadata = null,
    ) {}
}
