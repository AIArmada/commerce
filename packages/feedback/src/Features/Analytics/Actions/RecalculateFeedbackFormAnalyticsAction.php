<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Analytics\Actions;

use AIArmada\Feedback\Features\Analytics\Models\FeedbackFormAnalytics;
use AIArmada\Feedback\Features\Forms\Api\ReadsOwnedFormIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class RecalculateFeedbackFormAnalyticsAction
{
    public function __construct(
        private CalculateFeedbackFormAnalyticsAction $calculate,
        private ReadsOwnedFormIdentity $forms,
    ) {}

    public function execute(string $formId): FeedbackFormAnalytics
    {
        return (new FeedbackFormAnalytics)->getConnection()->transaction(function () use ($formId): FeedbackFormAnalytics {
            $form = $this->forms->requireOwned($formId, lock: true);

            $data = $this->calculate->execute($form->id);
            $analytics = FeedbackFormAnalytics::query()
                ->where('feedback_form_id', $form->id)
                ->first();

            if (! $analytics instanceof FeedbackFormAnalytics) {
                $analytics = new FeedbackFormAnalytics;
                $analytics->fill(['feedback_form_id' => $form->id]);
            } elseif (
                $analytics->owner_type !== $form->ownerType
                || (string) $analytics->owner_id !== (string) $form->ownerId
            ) {
                throw new AuthorizationException('Feedback analytics owner does not match its form owner.');
            }

            $analytics->fill([
                'total_responses' => $data->totalResponses,
                'completed_responses' => $data->completedResponses,
                'average_score' => $data->averageScore,
                'max_score' => $data->maxScore,
                'completion_rate' => $data->completionRate,
                'pending_review' => $data->pendingReview,
                'rejected' => $data->rejected,
                'spam' => $data->spam,
                'calculated_at' => CarbonImmutable::now(),
            ])->save();

            return $analytics;
        });
    }
}
