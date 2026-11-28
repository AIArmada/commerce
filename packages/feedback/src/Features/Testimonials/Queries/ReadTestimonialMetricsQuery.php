<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Testimonials\Queries;

use AIArmada\Feedback\Features\Testimonials\Api\ReadsTestimonialMetrics;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;

final class ReadTestimonialMetricsQuery implements ReadsTestimonialMetrics
{
    public function counts(): array
    {
        $row = FeedbackTestimonial::query()
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as pending, COUNT(CASE WHEN status = ? THEN 1 END) as approved, COUNT(CASE WHEN status = ? THEN 1 END) as published', ['pending', 'approved', 'published'])
            ->first();

        return [
            'pending' => (int) ($row?->getAttribute('pending') ?? 0),
            'approved' => (int) ($row?->getAttribute('approved') ?? 0),
            'published' => (int) ($row?->getAttribute('published') ?? 0),
        ];
    }
}
