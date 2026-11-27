<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use AIArmada\Feedback\Features\Testimonials\Api\ManagesTestimonialDependencies;
use Illuminate\Support\Facades\DB;

final readonly class DeleteFeedbackResponseDependentsAction
{
    public function __construct(private ManagesTestimonialDependencies $testimonials) {}

    public function execute(FeedbackResponse $response): void
    {
        if (FeedbackResponse::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(FeedbackResponse::class, $response->id);
        }

        DB::transaction(function () use ($response): void {
            $this->testimonials->deleteForResponse($response->id);
            $response->answers()->chunkById(200, fn ($answers): mixed => $answers->each->delete());
        });
    }
}
