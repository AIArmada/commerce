<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Events\FeedbackResponseReviewed;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use Carbon\CarbonImmutable;

final readonly class ReviewFeedbackResponseAction
{
    public function execute(FeedbackResponse $response): FeedbackResponse
    {
        return $response->getConnection()->transaction(function () use ($response): FeedbackResponse {
            if (FeedbackResponse::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackResponse::class, $response->id);
            }

            $response = FeedbackResponse::query()->whereKey($response->id)->lockForUpdate()->firstOrFail();

            if ($response->status->value === 'reviewed') {
                return $response;
            }

            $response->forceFill([
                'status' => 'reviewed',
                'reviewed_at' => CarbonImmutable::now(),
            ])->save();

            FeedbackResponseReviewed::dispatch($response);

            return $response;
        });
    }
}
