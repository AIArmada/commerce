<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Events\FeedbackResponseReviewed;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use Carbon\CarbonImmutable;

final class ReviewFeedbackResponseAction
{
    public function execute(FeedbackResponse $response): FeedbackResponse
    {
        $response = OwnerWriteGuard::findOrFailForOwner(FeedbackResponse::class, $response->id);

        $response->forceFill([
            'status' => 'reviewed',
            'reviewed_at' => CarbonImmutable::now(),
        ])->save();

        FeedbackResponseReviewed::dispatch($response);

        return $response;
    }
}
