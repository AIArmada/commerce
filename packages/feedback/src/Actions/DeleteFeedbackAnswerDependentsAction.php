<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackAnswer;
use Illuminate\Support\Facades\DB;

final readonly class DeleteFeedbackAnswerDependentsAction
{
    public function execute(FeedbackAnswer $answer): void
    {
        OwnerWriteGuard::findOrFailForOwner(FeedbackAnswer::class, $answer->id);

        DB::transaction(function () use ($answer): void {
            $answer->testimonials()->chunkById(200, fn ($testimonials): mixed => $testimonials->each->delete());
        });
    }
}
