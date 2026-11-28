<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Traits;

use AIArmada\Feedback\Features\Forms\Api\CreatesFormsFromTemplates;
use AIArmada\Feedback\Features\Forms\Api\ReadsSubjectForms;
use AIArmada\Feedback\Features\Responses\Api\ReadsSubjectFeedbackMetrics;
use AIArmada\Feedback\Features\Responses\Api\ReadsSubjectResponses;
use AIArmada\Feedback\Features\Testimonials\Api\ReadsSubjectTestimonials;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 *
 * @mixin TModel
 */
trait ReceivesFeedback
{
    /** @return list<string> */
    public function feedbackFormIds(): array
    {
        return app(ReadsSubjectForms::class)->ids($this->getMorphClass(), (string) $this->getKey());
    }

    /** @return list<string> */
    public function feedbackResponseIds(): array
    {
        return app(ReadsSubjectResponses::class)->subjectIds($this->getMorphClass(), (string) $this->getKey());
    }

    /** @return list<array{id: string, quote: string, display_name: ?string, display_title: ?string, display_organization: ?string, rating: ?float}> */
    public function publishedFeedbackTestimonials(): array
    {
        return app(ReadsSubjectTestimonials::class)->published($this->getMorphClass(), (string) $this->getKey());
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

    public function npsScore(?string $formId = null): ?int
    {
        return app(ReadsSubjectFeedbackMetrics::class)->nps(
            $this->getMorphClass(),
            (string) $this->getKey(),
            $formId,
        );
    }
}
