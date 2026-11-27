<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Events\FeedbackResponseMarkedSpam;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use Carbon\CarbonImmutable;

final readonly class MarkFeedbackResponseAsSpamAction
{
    public function execute(FeedbackResponse $response): FeedbackResponse
    {
        return $response->getConnection()->transaction(function () use ($response): FeedbackResponse {
            if (FeedbackResponse::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackResponse::class, $response->id);
            }

            $response = FeedbackResponse::query()->whereKey($response->id)->lockForUpdate()->firstOrFail();

            if ($response->status->value === 'spam') {
                return $response;
            }

            $response->forceFill([
                'status' => 'spam',
                'marked_spam_at' => CarbonImmutable::now(),
            ])->save();

            FeedbackResponseMarkedSpam::dispatch($response->id, $response->feedback_form_id, $response->owner_type, $response->owner_id);

            return $response;
        });
    }
}
