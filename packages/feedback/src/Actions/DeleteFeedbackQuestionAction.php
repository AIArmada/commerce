<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackQuestion;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use Illuminate\Support\Facades\DB;

final class DeleteFeedbackQuestionAction
{
    public function execute(FeedbackQuestion $question): void
    {
        $question = OwnerWriteGuard::findOrFailForOwner(FeedbackQuestion::class, $question->id);

        DB::transaction(function () use ($question): void {
            $answerIds = $question->answers()->pluck('id');

            if ($answerIds->isNotEmpty()) {
                FeedbackTestimonial::query()
                    ->whereIn('feedback_answer_id', $answerIds)
                    ->update(['feedback_answer_id' => null]);
            }

            $question->options()->delete();
            $question->answers()->delete();
            $question->delete();
        });
    }
}
