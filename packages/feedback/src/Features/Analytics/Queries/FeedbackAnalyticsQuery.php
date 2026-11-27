<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Analytics\Queries;

use AIArmada\CommerceSupport\Persistence\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Data\CsatResultData;
use AIArmada\Feedback\Data\FeedbackAnalyticsData;
use AIArmada\Feedback\Data\NpsResultData;
use AIArmada\Feedback\Features\Analytics\Models\FeedbackFormAnalytics;
use AIArmada\Feedback\Features\Responses\Models\FeedbackAnswer;
use AIArmada\Feedback\Features\Forms\Api\ReadsFormMetrics;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Responses\Api\ReadsResponseMetrics;
use AIArmada\Feedback\Features\Testimonials\Api\ReadsTestimonialMetrics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class FeedbackAnalyticsQuery
{
    public function __construct(
        private readonly NpsCalculator $npsCalculator,
        private readonly CsatCalculator $csatCalculator,
        private readonly RatingDistributionCalculator $ratingDistribution,
        private readonly CompletionRateCalculator $completionRate,
        private readonly ReadsResponseMetrics $responseMetrics,
        private readonly ReadsFormMetrics $formMetrics,
        private readonly ReadsTestimonialMetrics $testimonialMetrics,
    ) {}

    public function summaryForForm(FeedbackForm $form): FeedbackAnalyticsData
    {
        $aggregate = FeedbackFormAnalytics::query()
            ->where('feedback_form_id', $form->id)
            ->first();

        if ($aggregate instanceof FeedbackFormAnalytics) {
            return $aggregate->toData();
        }

        return $this->calculateLive($form);
    }

    public function calculateLive(FeedbackForm $form): FeedbackAnalyticsData
    {
        return $this->responseMetrics->summary($form->id);
    }

    public function averageForQuestion(FeedbackForm $form, string $questionKey): ?float
    {
        return $this->ratingDistribution->average($form, $questionKey);
    }

    public function distributionForQuestion(FeedbackForm $form, string $questionKey): array
    {
        return $this->ratingDistribution->calculate($form, $questionKey);
    }

    public function latestComments(FeedbackForm $form, int $limit = 10): Collection
    {
        return $this->commentsQuery($form)->limit($limit)->get();
    }

    public function completionRate(FeedbackForm $form): float
    {
        return $this->completionRate->calculate($form);
    }

    public function nps(FeedbackForm $form, ?string $questionKey = null): NpsResultData
    {
        return $this->npsCalculator->calculate($form, $questionKey);
    }

    public function csat(FeedbackForm $form, ?string $questionKey = null): CsatResultData
    {
        return $this->csatCalculator->calculate($form, $questionKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $owner = OwnerContext::resolve();
        OwnerContext::assertResolvedOrExplicitGlobal($owner);

        return OwnerCache::remember(
            $owner,
            'feedback.dashboard',
            (int) config('feedback.analytics.dashboard_cache_ttl', 30),
            function (): array {
                $responseMetrics = $this->responseMetrics->dashboardMetrics();

                return [
                    'overview' => array_merge($this->formMetrics->counts(), [
                        'total_responses' => $responseMetrics['total_responses'],
                        'submitted_responses' => $responseMetrics['submitted_responses'],
                    ]),
                    'response_trend' => $responseMetrics['response_trend'],
                    'average_rating' => $responseMetrics['average_rating'],
                    'nps' => $this->npsCalculator->calculate(),
                    'csat' => $this->csatCalculator->calculate(),
                    'rating_distribution' => $this->ratingDistribution->calculate(),
                    'latest_comments' => $this->commentsQuery()->limit(10)->get()->toArray(),
                    'completion_rate' => $this->completionRate->calculate(),
                    'testimonials' => $this->testimonialMetrics->counts(),
                ];
            },
        );
    }

    /**
     * @return Builder<FeedbackAnswer>
     */
    private function commentsQuery(?FeedbackForm $form = null): Builder
    {
        /** @var Builder<FeedbackAnswer> $query */
        $query = FeedbackAnswer::query()
            ->whereHas('response', function (Builder $q) use ($form): void {
                $q->whereIn('status', ['submitted', 'reviewed']);

                if ($form !== null) {
                    $q->where('feedback_form_id', $form->id);
                }
            })
            ->whereNotNull('text_value')
            ->where('text_value', '!=', '')
            ->with(['response.form', 'question'])
            ->latest();

        return $query;
    }
}
