<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Analytics\EntryPoints\Listeners;

use AIArmada\Feedback\Events\FeedbackResponseMarkedSpam;
use AIArmada\Feedback\Events\FeedbackResponseRejected;
use AIArmada\Feedback\Events\FeedbackResponseReviewed;
use AIArmada\Feedback\Events\FeedbackResponseStarted;
use AIArmada\Feedback\Events\FeedbackResponseSubmitted;
use AIArmada\Feedback\Features\Analytics\EntryPoints\Jobs\RecalculateFeedbackFormAnalyticsJob;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

final class QueueFeedbackAnalyticsRecalculation implements ShouldHandleEventsAfterCommit
{
    public function handle(
        FeedbackResponseStarted | FeedbackResponseSubmitted | FeedbackResponseReviewed | FeedbackResponseRejected | FeedbackResponseMarkedSpam $event,
    ): void {
        dispatch(new RecalculateFeedbackFormAnalyticsJob(
            formId: $event->formId,
            ownerType: $event->ownerType,
            ownerId: $event->ownerId,
            ownerIsGlobal: $event->ownerType === null && $event->ownerId === null,
        ))->afterCommit();
    }
}
