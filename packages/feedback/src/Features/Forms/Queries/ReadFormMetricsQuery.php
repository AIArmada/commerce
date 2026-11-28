<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Queries;

use AIArmada\Feedback\Features\Forms\Api\ReadsFormMetrics;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;

final class ReadFormMetricsQuery implements ReadsFormMetrics
{
    public function counts(): array
    {
        $row = FeedbackForm::query()
            ->selectRaw('COUNT(*) as total_forms, COUNT(CASE WHEN status = ? THEN 1 END) as published_forms', ['published'])
            ->first();

        return [
            'total_forms' => (int) ($row?->getAttribute('total_forms') ?? 0),
            'published_forms' => (int) ($row?->getAttribute('published_forms') ?? 0),
        ];
    }
}
