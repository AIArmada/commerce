<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Analytics;

use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Contracts\FeedbackAnalyticsCalculator;
use AIArmada\Feedback\Data\FeedbackAnalyticsData;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackFormAnalytics;
use AIArmada\Feedback\Models\FeedbackResponse;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class FeedbackAnalyticsService implements FeedbackAnalyticsCalculator
{
    public function __construct(
        private readonly NpsCalculator $npsCalculator,
        private readonly CsatCalculator $csatCalculator,
        private readonly RatingDistributionCalculator $ratingDistribution,
        private readonly CompletionRateCalculator $completionRate,
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
        $total = $this->responseQuery($form)->count();
        $completed = $this->responseQuery($form)->where('status', 'submitted')->count();
        $avgScore = $this->responseQuery($form)->whereNotNull('score')->avg('score');
        $maxScore = $this->responseQuery($form)->whereNotNull('max_score')->max('max_score');

        return new FeedbackAnalyticsData(
            totalResponses: $total,
            completedResponses: $completed,
            averageScore: $avgScore !== null ? round((float) $avgScore, 2) : null,
            maxScore: $maxScore !== null ? round((float) $maxScore, 2) : null,
            completionRate: $this->completionRate->calculate($form),
            pendingReview: $this->responseQuery($form)->where('status', 'submitted')->count(),
            rejected: $this->responseQuery($form)->where('status', 'rejected')->count(),
            spam: $this->responseQuery($form)->where('status', 'spam')->count(),
        );
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

    public function nps(FeedbackForm $form, ?string $questionKey = null): NpsCalculator
    {
        return $this->npsCalculator;
    }

    public function csat(FeedbackForm $form, ?string $questionKey = null): CsatCalculator
    {
        return $this->csatCalculator;
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
                $formQuery = FeedbackForm::query();
                $responseQuery = FeedbackResponse::query();
                $submittedResponseQuery = (clone $responseQuery)->where('status', 'submitted');

                $testimonialQuery = FeedbackTestimonial::query();

                return [
                    'overview' => [
                        'total_forms' => (clone $formQuery)->count(),
                        'published_forms' => (clone $formQuery)->where('status', 'published')->count(),
                        'total_responses' => (clone $responseQuery)->count(),
                        'submitted_responses' => (clone $submittedResponseQuery)->count(),
                    ],
                    'response_trend' => (clone $submittedResponseQuery)
                        ->selectRaw('DATE(submitted_at) as date, COUNT(*) as count')
                        ->groupBy('date')
                        ->orderBy('date')
                        ->limit(30)
                        ->pluck('count', 'date')
                        ->toArray(),
                    'average_rating' => (clone $submittedResponseQuery)
                        ->whereNotNull('score')
                        ->avg('score'),
                    'nps' => $this->npsCalculator->calculate(),
                    'csat' => $this->csatCalculator->calculate(),
                    'rating_distribution' => $this->ratingDistribution->calculate(),
                    'latest_comments' => $this->commentsQuery()->limit(10)->get()->toArray(),
                    'completion_rate' => $this->completionRate->calculate(),
                    'testimonials' => [
                        'pending' => (clone $testimonialQuery)->where('status', 'pending')->count(),
                        'approved' => (clone $testimonialQuery)->where('status', 'approved')->count(),
                        'published' => (clone $testimonialQuery)->where('status', 'published')->count(),
                    ],
                ];
            },
        );
    }

    /**
     * @return Builder<FeedbackResponse>
     */
    private function responseQuery(FeedbackForm $form): Builder
    {
        /** @var Builder<FeedbackResponse> */
        return FeedbackResponse::query()->where('feedback_form_id', $form->id);
    }

    /**
     * @return Builder<FeedbackAnswer>
     */
    private function commentsQuery(?FeedbackForm $form = null): Builder
    {
        /** @var Builder<FeedbackAnswer> $query */
        $query = FeedbackAnswer::query()
            ->whereHas('response', function (Builder $q) use ($form): void {
                $q->where('status', 'submitted');

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
