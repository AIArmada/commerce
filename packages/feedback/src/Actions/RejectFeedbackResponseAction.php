<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Events\FeedbackResponseRejected;
use AIArmada\Feedback\Models\FeedbackResponse;
use Carbon\CarbonImmutable;

final class RejectFeedbackResponseAction
{
    public function execute(FeedbackResponse $response): FeedbackResponse
    {
        $response->forceFill([
            'status' => 'rejected',
            'rejected_at' => CarbonImmutable::now(),
        ])->save();

        FeedbackResponseRejected::dispatch($response);

        return $response;
    }
}
