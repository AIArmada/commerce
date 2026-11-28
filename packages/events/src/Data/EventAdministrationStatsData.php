<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

final readonly class EventAdministrationStatsData
{
    public function __construct(
        public int $totalEvents,
        public int $publishedEvents,
        public int $upcomingOccurrences,
        public int $totalRegistrations,
        public int $totalAttendances,
    ) {}
}
