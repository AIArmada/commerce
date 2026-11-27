<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackResponse;
use Illuminate\Support\Facades\DB;

final readonly class DeleteFeedbackResponseDependentsAction
{
    public function execute(FeedbackResponse $response): void
    {
        OwnerWriteGuard::findOrFailForOwner(FeedbackResponse::class, $response->id);

        DB::transaction(function () use ($response): void {
            $response->testimonials()->chunkById(200, fn ($testimonials): mixed => $testimonials->each->delete());
            $response->answers()->chunkById(200, fn ($answers): mixed => $answers->each->delete());
        });
    }
}
