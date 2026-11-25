<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Api;

final readonly class EventCreationData
{
    /**
     * @param  array<int, array<string, mixed>>  $involvements
     * @param  array<int, array<string, mixed>>  $classifications
     */
    public function __construct(
        public string $ownerType,
        public string $ownerId,
        public string $title,
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $type = null,
        public string $status = 'draft',
        public string $visibility = 'public',
        public string $deliveryMode = 'physical',
        public string $timezone = 'UTC',
        public ?string $startsAt = null,
        public ?string $endsAt = null,
        public array $involvements = [],
        public array $classifications = [],
    ) {}
}
