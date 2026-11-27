<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Counters\Api;

interface ReadsEngagementCounters
{
    public function count(string $subjectType, string $subjectId, string $counterType, ?string $counterKey = null): int;
}
