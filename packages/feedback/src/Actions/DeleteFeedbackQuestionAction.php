<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackQuestion;
use Illuminate\Support\Facades\DB;

final readonly class DeleteFeedbackQuestionAction
{
    public function execute(FeedbackQuestion $question): void
    {
        $guarded = OwnerWriteGuard::findOrFailForOwner(FeedbackQuestion::class, $question->id);

        DB::transaction(function () use ($guarded): void {
            FeedbackQuestion::query()->whereKey($guarded->id)->lockForUpdate()->firstOrFail()->delete();
        });
    }
}
