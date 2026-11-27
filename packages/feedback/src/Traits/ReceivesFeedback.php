<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Traits;

use AIArmada\Feedback\Features\Forms\Actions\CreateFeedbackFormFromTemplateAction;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Forms\Models\FeedbackTemplate;
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

    public function createFeedbackFormFromTemplate(string | FeedbackTemplate $template, array $overrides = []): FeedbackForm
    {
        if (is_string($template)) {
            $template = FeedbackTemplate::where('slug', $template)->firstOrFail();
        }

        return app(CreateFeedbackFormFromTemplateAction::class)->execute(
            $template,
            array_merge($overrides, [
                'subject_type' => $this->getMorphClass(),
                'subject_id' => $this->getKey(),
            ]),
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
