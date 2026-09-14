<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Events\FeedbackResponseMarkedSpam;
use AIArmada\Feedback\Models\FeedbackResponse;
use Carbon\CarbonImmutable;

final class MarkFeedbackResponseAsSpamAction
{
    public function execute(FeedbackResponse $response): FeedbackResponse
    {
        $response = OwnerWriteGuard::findOrFailForOwner(FeedbackResponse::class, $response->id);

        $response->forceFill([
            'status' => 'spam',
            'marked_spam_at' => CarbonImmutable::now(),
        ])->save();

        FeedbackResponseMarkedSpam::dispatch($response);

        return $response;
    }
}
