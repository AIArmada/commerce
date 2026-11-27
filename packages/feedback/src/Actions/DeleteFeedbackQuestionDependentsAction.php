<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackQuestion;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class DeleteFeedbackQuestionDependentsAction
{
    public function execute(FeedbackQuestion $question): void
    {
        OwnerWriteGuard::findOrFailForOwner(FeedbackQuestion::class, $question->id);

        DB::transaction(function () use ($question): void {
            FeedbackTestimonial::query()
                ->whereHas('answer', fn (Builder $query) => $query->where('feedback_question_id', $question->id))
                ->update(['feedback_answer_id' => null]);

            $question->options()->delete();
            $question->answers()->delete();
        });
    }
}
