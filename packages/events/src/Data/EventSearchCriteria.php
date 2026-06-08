<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class EventSearchCriteria extends Data
{
    /**
     * @param  array<int, string>  $statuses
     * @param  array<int, string>  $moderationStatuses
     * @param  array<int, string>  $visibilities
     * @param  array<int, string>  $structures
     * @param  array<int, string>  $referenceKinds
     * @param  array<int, string>  $classificationGroups
     * @param  array<int, string>  $assetRoles
     */
    public function __construct(
        public readonly ?string $term = null,
        public readonly array $statuses = [],
        public readonly array $moderationStatuses = [],
        public readonly array $visibilities = [],
        public readonly array $structures = [],
        public readonly array $referenceKinds = [],
        public readonly array $classificationGroups = [],
        public readonly array $assetRoles = [],
        public readonly ?CarbonImmutable $publishedAfter = null,
        public readonly ?CarbonImmutable $publishedBefore = null,
        public readonly int $page = 1,
        public readonly int $perPage = 20,
        public readonly ?string $sort = null,
        public readonly string $direction = 'desc',
        public readonly bool $includeGlobal = false,
    ) {}
}
