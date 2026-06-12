<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Traits;

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

    public function createFeedbackFormFromTemplate(string | FeedbackTemplate $template, array $overrides = []): FeedbackForm
    {
        if (is_string($template)) {
            $template = FeedbackTemplate::where('slug', $template)->firstOrFail();
        }

        return FeedbackForm::create(array_merge(
            [
                'name' => $template->name,
                'purpose' => $template->purpose,
                'status' => 'draft',
                'subject_type' => $this->getMorphClass(),
                'subject_id' => $this->getKey(),
            ],
            $overrides,
        ));
    }

    public function averageFeedbackScore(?string $questionKey = null): ?float
    {
        $query = $this->feedbackResponses()
            ->where('status', 'submitted');

        if ($questionKey !== null) {
            return $query->whereHas('answers', function ($q) use ($questionKey): void {
                $q->whereHas('question', function ($qq) use ($questionKey): void {
                    $qq->where('key', $questionKey);
                })->whereNotNull('score');
            })->avg('score');
        }

        return $query->whereNotNull('score')->avg('score');
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
