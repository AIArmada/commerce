<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Traits;

use AIArmada\Feedback\Actions\CreateFeedbackFormFromTemplateAction;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackResponse;
use AIArmada\Feedback\Models\FeedbackTemplate;
use AIArmada\Feedback\Models\FeedbackTestimonial;
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
        if ($questionKey !== null) {
            $avg = FeedbackAnswer::query()
                ->whereHas('response', function ($q): void {
                    $q->where('subject_type', $this->getMorphClass())
                        ->where('subject_id', $this->getKey())
                        ->where('status', 'submitted');
                })
                ->whereHas('question', function ($q) use ($questionKey): void {
                    $q->where('key', $questionKey);
                })
                ->whereNotNull('score')
                ->avg('score');

            return $avg !== null ? (float) $avg : null;
        }

        $avg = $this->feedbackResponses()
            ->where('status', 'submitted')
            ->whereNotNull('score')
            ->avg('score');

        return $avg !== null ? (float) $avg : null;
    }

    public function npsScore(?FeedbackForm $form = null): ?int
    {
        $query = $this->feedbackResponses()
            ->where('status', 'submitted')
            ->whereNotNull('score');

        if ($form !== null) {
            $query->where('feedback_form_id', $form->id);
        }

        $counts = (clone $query)
            ->selectRaw('
                COUNT(CASE WHEN score >= 9 THEN 1 END) as promoters,
                COUNT(CASE WHEN score BETWEEN 7 AND 8 THEN 1 END) as passives,
                COUNT(CASE WHEN score <= 6 THEN 1 END) as detractors,
                COUNT(*) as total
            ')
            ->first();

        if (! $counts || $counts->total === 0) {
            return null;
        }

        $promoterPct = ($counts->promoters / $counts->total) * 100;
        $detractorPct = ($counts->detractors / $counts->total) * 100;

        return (int) round($promoterPct - $detractorPct);
    }
}
