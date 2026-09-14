<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackQuestion;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class DeleteFeedbackQuestionAction
{
    public function execute(FeedbackQuestion $question): void
    {
        $question = OwnerWriteGuard::findOrFailForOwner(FeedbackQuestion::class, $question->id);

        DB::transaction(function () use ($question): void {
            FeedbackTestimonial::query()
                ->whereHas('answer', function (Builder $query) use ($question): void {
                    $query->where('feedback_question_id', $question->id);
                })
                ->update(['feedback_answer_id' => null]);

            $question->options()->delete();
            $question->answers()->delete();
            $question->delete();
        });
    }
}
