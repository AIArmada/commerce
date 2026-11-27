<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Testimonials\Queries;

use AIArmada\Feedback\Features\Testimonials\Api\ReadsSubjectTestimonials;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;

final readonly class ReadSubjectTestimonialsQuery implements ReadsSubjectTestimonials
{
    public function published(string $type, string $id): array
    {
        return FeedbackTestimonial::query()->published()
            ->where('subject_type', $type)->where('subject_id', $id)
            ->orderByDesc('published_at')->orderByDesc('id')
            ->get(['id', 'quote', 'display_name', 'display_title', 'display_organization', 'rating'])
            ->map(static fn (FeedbackTestimonial $record): array => [
                'id' => $record->id,
                'quote' => $record->quote,
                'display_name' => $record->display_name,
                'display_title' => $record->display_title,
                'display_organization' => $record->display_organization,
                'rating' => $record->rating === null ? null : (float) $record->rating,
            ])->values()->all();
    }
}
