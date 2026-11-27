<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Traits;

use AIArmada\Feedback\Features\Forms\Api\CreatesFormsFromTemplates;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Responses\Api\ReadsSubjectFeedbackMetrics;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @template TModel of Model
 *
 * @mixin TModel
 */
trait ReceivesFeedback
{
    public function feedbackForms(): MorphMany
    {
        return $this->morphMany(FeedbackForm::class, 'subject');
    }

    public function feedbackResponses(): MorphMany
    {
        return $this->morphMany(FeedbackResponse::class, 'subject');
    }

    public function feedbackTestimonials(): MorphMany
    {
        return $this->morphMany(FeedbackTestimonial::class, 'subject');
    }

    public function publishedFeedbackTestimonials(): MorphMany
    {
        return $this->feedbackTestimonials()->published();
    }

    public function createFeedbackFormFromTemplate(string $templateSlug, array $overrides = []): string
    {
        return app(CreatesFormsFromTemplates::class)->createForSubject(
            $templateSlug,
            $this->getMorphClass(),
            (string) $this->getKey(),
            $overrides,
        );
    }

    public function averageFeedbackScore(?string $questionKey = null): ?float
    {
        return app(ReadsSubjectFeedbackMetrics::class)->average(
            $this->getMorphClass(),
            (string) $this->getKey(),
            $questionKey,
        );
    }

    public function npsScore(?FeedbackForm $form = null): ?int
    {
        return app(ReadsSubjectFeedbackMetrics::class)->nps(
            $this->getMorphClass(),
            (string) $this->getKey(),
            $form?->id,
        );
    }
}
