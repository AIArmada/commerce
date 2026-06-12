<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Events\FeedbackResponseReviewed;
use AIArmada\Feedback\Models\FeedbackResponse;
use Carbon\CarbonImmutable;

final class ReviewFeedbackResponseAction
{
    public function execute(FeedbackResponse $response): FeedbackResponse
    {
        $response->forceFill([
            'status' => 'reviewed',
            'reviewed_at' => CarbonImmutable::now(),
        ])->save();

        FeedbackResponseReviewed::dispatch($response);

        return $response;
    }
}
