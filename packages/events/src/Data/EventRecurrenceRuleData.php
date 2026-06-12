<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use Spatie\LaravelData\Data;

final class EventRecurrenceRuleData extends Data
{
    public function __construct(
        public readonly string $recurrence_type,
        public readonly string $frequency,
        public readonly int $interval = 1,
        public readonly ?array $days_of_week = null,
        public readonly ?array $days_of_month = null,
        public readonly ?array $months_of_year = null,
        public readonly ?string $starts_on = null,
        public readonly ?string $ends_on = null,
        public readonly ?int $max_occurrences = null,
        public readonly ?string $timezone = null,
        public readonly ?string $time_mode = null,
        public readonly ?string $starts_at_time = null,
        public readonly ?string $ends_at_time = null,
        public readonly ?string $anchor_type = null,
        public readonly ?string $anchor_code = null,
        public readonly ?string $relation = null,
        public readonly ?int $offset_minutes = null,
        public readonly ?string $rrule_text = null,
        public readonly ?string $human_readable_rule = null,
        public readonly ?string $code = null,
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly string $status = 'active',
        public readonly string $visibility = 'public',
        public readonly ?array $metadata = null,
    ) {}
}
