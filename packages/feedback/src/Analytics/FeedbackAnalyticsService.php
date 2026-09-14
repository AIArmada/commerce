<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Analytics;

use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Contracts\FeedbackAnalyticsCalculator;
use AIArmada\Feedback\Data\CsatResultData;
use AIArmada\Feedback\Data\FeedbackAnalyticsData;
use AIArmada\Feedback\Data\NpsResultData;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackFormAnalytics;
use AIArmada\Feedback\Models\FeedbackResponse;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        $submitted = FeedbackResponseStatus::Submitted->value;
        $reviewed = FeedbackResponseStatus::Reviewed->value;
        $rejected = FeedbackResponseStatus::Rejected->value;
        $spam = FeedbackResponseStatus::Spam->value;

        $row = $this->responseQuery($form)
            ->selectRaw(
                'COUNT(*) as total, ' .
                'COUNT(CASE WHEN status IN (?, ?) THEN 1 END) as completed, ' .
                'AVG(score) as avg_score, ' .
                'MAX(max_score) as max_score, ' .
                'COUNT(CASE WHEN status = ? THEN 1 END) as pending, ' .
                'COUNT(CASE WHEN status = ? THEN 1 END) as rejected, ' .
                'COUNT(CASE WHEN status = ? THEN 1 END) as spam',
                [$submitted, $reviewed, $submitted, $rejected, $spam],
            )
            ->first();

        $total = (int) ($row?->getAttribute('total') ?? 0);
        $completed = (int) ($row?->getAttribute('completed') ?? 0);
        $avgScore = $row?->getAttribute('avg_score');
        $maxScore = $row?->getAttribute('max_score');

        return new FeedbackAnalyticsData(
            totalResponses: $total,
            completedResponses: $completed,
            averageScore: $avgScore !== null ? round((float) $avgScore, 2) : null,
            maxScore: $maxScore !== null ? round((float) $maxScore, 2) : null,
            completionRate: $total > 0 ? round(($completed / $total) * 100, 2) : 0.0,
            pendingReview: (int) ($row?->getAttribute('pending') ?? 0),
            rejected: (int) ($row?->getAttribute('rejected') ?? 0),
            spam: (int) ($row?->getAttribute('spam') ?? 0),
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
                        ->selectRaw($this->trendDateExpression() . ' as date, COUNT(*) as count')
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

    private function trendDateExpression(): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => 'date(submitted_at)',
            'sqlsrv' => 'CAST(submitted_at AS DATE)',
            default => 'DATE(submitted_at)',
        };
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
