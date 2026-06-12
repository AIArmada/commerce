<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Traits;

use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @template TModel of Model
 *
 * @mixin TModel
 */
trait GivesFeedback
{
    public function feedbackResponses(): MorphMany
    {
        return $this->morphMany(FeedbackResponse::class, 'respondent');
    }

    public function hasSubmittedFeedbackFor(Model $subject, ?FeedbackForm $form = null): bool
    {
        $query = $this->feedbackResponses()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('status', 'submitted');

        if ($form !== null) {
            $query->where('feedback_form_id', $form->id);
        }

        return $query->exists();
    }

    public function latestFeedbackResponseFor(Model $subject, ?FeedbackForm $form = null): ?FeedbackResponse
    {
        $query = $this->feedbackResponses()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());

        if ($form !== null) {
            $query->where('feedback_form_id', $form->id);
        }

        return $query->latest()->first();
    }
}
