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
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
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
        return $this->responseMetrics->average($form->id, $questionKey);
    }

    public function distributionForQuestion(FeedbackForm $form, string $questionKey): array
    {
        return $this->responseMetrics->distribution($form->id, $questionKey);
    }

    public function latestComments(FeedbackForm $form, int $limit = 10): array
    {
        return $this->responseMetrics->latestComments($form->id, $limit);
    }

    public function completionRate(FeedbackForm $form): float
    {
        return $this->completionRate->calculate($form);
    }

    public function nps(FeedbackForm $form, ?string $questionKey = null): NpsResultData
    {
        return $this->responseMetrics->nps($form->id, $questionKey);
    }

    public function csat(FeedbackForm $form, ?string $questionKey = null): CsatResultData
    {
        return $this->responseMetrics->csat($form->id, $questionKey);
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
                    'nps' => $this->responseMetrics->nps(),
                    'csat' => $this->responseMetrics->csat(),
                    'rating_distribution' => $this->responseMetrics->distribution(),
                    'latest_comments' => $this->responseMetrics->latestComments(),
                    'completion_rate' => $this->completionRate->calculate(),
                    'testimonials' => $this->testimonialMetrics->counts(),
                ];
            },
        );
    }

}
