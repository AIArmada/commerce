<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Analytics\Queries;

use AIArmada\CommerceSupport\Persistence\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Data\CsatResultData;
use AIArmada\Feedback\Data\FeedbackAnalyticsData;
use AIArmada\Feedback\Data\NpsResultData;
use AIArmada\Feedback\Features\Analytics\Models\FeedbackFormAnalytics;
use AIArmada\Feedback\Features\Forms\Api\ReadsFormMetrics;
use AIArmada\Feedback\Features\Responses\Api\ReadsResponseMetrics;
use AIArmada\Feedback\Features\Testimonials\Api\ReadsTestimonialMetrics;

final class FeedbackAnalyticsQuery
{
    public function __construct(
        private readonly CompletionRateCalculator $completionRate,
        private readonly ReadsResponseMetrics $responseMetrics,
        private readonly ReadsFormMetrics $formMetrics,
        private readonly ReadsTestimonialMetrics $testimonialMetrics,
    ) {}

    public function summaryForForm(string $formId): FeedbackAnalyticsData
    {
        $aggregate = FeedbackFormAnalytics::query()
            ->where('feedback_form_id', $formId)
            ->first();

        if ($aggregate instanceof FeedbackFormAnalytics) {
            return $aggregate->toData();
        }

        return $this->calculateLive($formId);
    }

    public function calculateLive(string $formId): FeedbackAnalyticsData
    {
        return $this->responseMetrics->summary($formId);
    }

    public function averageForQuestion(string $formId, string $questionKey): ?float
    {
        return $this->responseMetrics->average($formId, $questionKey);
    }

    public function distributionForQuestion(string $formId, string $questionKey): array
    {
        return $this->responseMetrics->distribution($formId, $questionKey);
    }

    public function latestComments(string $formId, int $limit = 10): array
    {
        return $this->responseMetrics->latestComments($formId, $limit);
    }

    public function completionRate(string $formId): float
    {
        return $this->completionRate->calculate($formId);
    }

    public function nps(string $formId, ?string $questionKey = null): NpsResultData
    {
        return $this->responseMetrics->nps($formId, $questionKey);
    }

    public function csat(string $formId, ?string $questionKey = null): CsatResultData
    {
        return $this->responseMetrics->csat($formId, $questionKey);
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
                $totalResponses = (int) $responseMetrics['total_responses'];
                $submittedResponses = (int) $responseMetrics['submitted_responses'];

                return [
                    'overview' => array_merge($this->formMetrics->counts(), [
                        'total_responses' => $responseMetrics['total_responses'],
                        'submitted_responses' => $responseMetrics['submitted_responses'],
                    ]),
                    'response_trend' => $responseMetrics['response_trend'],
                    'average_rating' => $responseMetrics['average_rating'],
                    'nps' => $this->responseMetrics->nps(),
                    'csat' => $this->responseMetrics->csat(),
                    'rating_distribution' => $this->responseMetrics->distribution(),
                    'latest_comments' => $this->responseMetrics->latestComments(),
                    'completion_rate' => $totalResponses > 0
                        ? round(($submittedResponses / $totalResponses) * 100, 2)
                        : 0.0,
                    'testimonials' => $this->testimonialMetrics->counts(),
                ];
            },
        );
    }
}
