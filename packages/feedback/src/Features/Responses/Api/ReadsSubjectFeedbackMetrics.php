<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Api;

interface ReadsSubjectFeedbackMetrics
{
    public function average(string $subjectType, string $subjectId, ?string $questionKey = null): ?float;

    public function nps(string $subjectType, string $subjectId, ?string $formId = null): ?int;
}
