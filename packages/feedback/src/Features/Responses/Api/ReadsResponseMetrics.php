<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Api;

use AIArmada\Feedback\Data\CsatResultData;
use AIArmada\Feedback\Data\FeedbackAnalyticsData;
use AIArmada\Feedback\Data\NpsResultData;

interface ReadsResponseMetrics
{
    public function summary(?string $formId = null): FeedbackAnalyticsData;

    /** @return array<string, mixed> */
    public function dashboardMetrics(): array;

    public function nps(?string $formId = null, ?string $questionKey = null): NpsResultData;

    public function csat(?string $formId = null, ?string $questionKey = null): CsatResultData;

    /** @return array<int|string, int> */
    public function distribution(?string $formId = null, ?string $questionKey = null): array;

    public function average(?string $formId = null, ?string $questionKey = null): ?float;

    /** @return list<array{id: string, text_value: string}> */
    public function latestComments(?string $formId = null, int $limit = 10): array;
}
