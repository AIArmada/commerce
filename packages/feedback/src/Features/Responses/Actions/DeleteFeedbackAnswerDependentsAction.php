<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Responses\Models\FeedbackAnswer;
use AIArmada\Feedback\Features\Testimonials\Api\ManagesTestimonialDependencies;

final readonly class DeleteFeedbackAnswerDependentsAction
{
    public function __construct(private ManagesTestimonialDependencies $testimonials) {}

    public function execute(FeedbackAnswer $answer): void
    {
        if (FeedbackAnswer::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(FeedbackAnswer::class, $answer->id);
        }

        $answer->getConnection()->transaction(function () use ($answer): void {
            $this->testimonials->deleteForAnswer($answer->id);
        });
    }
}
