<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Api;

use Carbon\CarbonImmutable;

final readonly class EventScopeData
{
    public function __construct(
        public string $scopeType,
        public string $scopeId,
        public string $eventId,
        public ?string $occurrenceId,
        public ?string $sessionId,
        public string $morphClass,
        public ?string $title,
        public ?string $status,
        public ?int $capacity,
        public ?CarbonImmutable $startsAt,
        public string $pricingMode,
        public string $registrationMode,
        public bool $shouldIssuePassesForFree,
    ) {}
}
