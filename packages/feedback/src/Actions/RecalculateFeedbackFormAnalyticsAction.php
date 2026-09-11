<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackFormAnalytics;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

final class RecalculateFeedbackFormAnalyticsAction
{
    public function __construct(
        private readonly CalculateFeedbackFormAnalyticsAction $calculate,
    ) {}

    public function execute(FeedbackForm $form): FeedbackFormAnalytics
    {
        $this->assertOwnerContextMatchesForm($form);

        $data = $this->calculate->execute($form);
        $analytics = FeedbackFormAnalytics::query()
            ->where('feedback_form_id', $form->id)
            ->first();

        if (! $analytics instanceof FeedbackFormAnalytics) {
            $analytics = new FeedbackFormAnalytics;
            $analytics->fill(['feedback_form_id' => $form->id]);
        } elseif (
            $analytics->owner_type !== $form->owner_type
            || (string) $analytics->owner_id !== (string) $form->owner_id
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
    }

    private function assertOwnerContextMatchesForm(FeedbackForm $form): void
    {
        if (! FeedbackForm::ownerScopeConfig()->enabled) {
            return;
        }

        $owner = OwnerContext::resolve();

        if ($form->owner_type === null && $form->owner_id === null) {
            if (! OwnerContext::isExplicitGlobal()) {
                throw new AuthorizationException('Explicit global owner context is required for global feedback analytics.');
            }

            return;
        }

        if (
            $owner === null
            || $form->owner_type !== $owner->getMorphClass()
            || (string) $form->owner_id !== (string) $owner->getKey()
        ) {
            throw new AuthorizationException('Feedback analytics requires the form owner context.');
        }
    }
}
