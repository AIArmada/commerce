<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Events\FeedbackResponseMarkedSpam;
use AIArmada\Feedback\Models\FeedbackResponse;
use Carbon\CarbonImmutable;

final class MarkFeedbackResponseAsSpamAction
{
    public function execute(FeedbackResponse $response): FeedbackResponse
    {
        $response->forceFill([
            'status' => 'spam',
            'marked_spam_at' => CarbonImmutable::now(),
        ])->save();

        FeedbackResponseMarkedSpam::dispatch($response);

        return $response;
    }
}
