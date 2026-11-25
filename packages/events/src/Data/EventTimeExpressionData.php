<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use Carbon\CarbonImmutable;

final readonly class EventTimeExpressionData
{
    /**
     * @param  array<string, mixed>|null  $resolverContext
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public string $eventId,
        public ?string $occurrenceId,
        public ?string $sessionId,
        public string $timeMode,
        public ?string $anchorType,
        public ?string $anchorCode,
        public ?string $relation,
        public ?int $offsetMinutes,
        public ?string $displayLabel,
        public ?string $resolverClass,
        public ?array $resolverContext,
        public ?CarbonImmutable $resolvedStartsAt,
        public ?CarbonImmutable $resolvedEndsAt,
        public ?CarbonImmutable $resolvedAt,
        public ?array $metadata = null,
    ) {}
}
