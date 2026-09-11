<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Listeners;

use AIArmada\Feedback\Events\FeedbackResponseMarkedSpam;
use AIArmada\Feedback\Events\FeedbackResponseRejected;
use AIArmada\Feedback\Events\FeedbackResponseReviewed;
use AIArmada\Feedback\Events\FeedbackResponseStarted;
use AIArmada\Feedback\Events\FeedbackResponseSubmitted;
use AIArmada\Feedback\Jobs\RecalculateFeedbackFormAnalyticsJob;

final class QueueFeedbackAnalyticsRecalculation
{
    public function handle(
        FeedbackResponseStarted | FeedbackResponseSubmitted | FeedbackResponseReviewed | FeedbackResponseRejected | FeedbackResponseMarkedSpam $event,
    ): void {
        dispatch(RecalculateFeedbackFormAnalyticsJob::forResponse($event->response))->afterCommit();
    }
}
