<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use AIArmada\Feedback\Features\Testimonials\Api\ManagesTestimonialDependencies;

final readonly class DeleteFeedbackResponseDependentsAction
{
    public function __construct(private ManagesTestimonialDependencies $testimonials) {}

    public function execute(FeedbackResponse $response): void
    {
        if (FeedbackResponse::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(FeedbackResponse::class, $response->id);
        }

        $response->getConnection()->transaction(function () use ($response): void {
            $answerIds = $response->answers()->pluck('id')->all();

            $this->testimonials->deleteForResponses([$response->id], $answerIds);

            $response->answers()->delete();
        });
    }
}
