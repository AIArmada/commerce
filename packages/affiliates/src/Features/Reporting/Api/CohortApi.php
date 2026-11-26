<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting\Api;

use Carbon\CarbonImmutable;

interface CohortApi
{
    public function analyzeMonthly(?CarbonImmutable $from = null, ?CarbonImmutable $to = null, int $monthsToTrack = 12): array;

    public function calculateRetentionCurve(?CarbonImmutable $from = null, ?CarbonImmutable $to = null, int $maxMonths = 12): array;

    public function calculateLtv(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array;

    public function compareCohorts(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array;

    public function analyzeBySource(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array;
}
