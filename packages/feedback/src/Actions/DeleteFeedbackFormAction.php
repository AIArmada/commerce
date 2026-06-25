<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackQuestionOption;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use Illuminate\Support\Facades\DB;

final class DeleteFeedbackFormAction
{
    public function execute(FeedbackForm $form): void
    {
        $form = OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id);

        DB::transaction(function () use ($form): void {
            $questionIds = $form->questions()->pluck('id');
            $responseIds = $form->responses()->pluck('id');
            $answerIds = FeedbackAnswer::query()
                ->whereIn('feedback_response_id', $responseIds)
                ->pluck('id');

            FeedbackTestimonial::query()
                ->where(function ($query) use ($responseIds, $answerIds): void {
                    $query->whereIn('feedback_response_id', $responseIds)
                        ->orWhereIn('feedback_answer_id', $answerIds);
                })
                ->get()
                ->each
                ->delete();

            FeedbackAnswer::query()
                ->whereIn('feedback_response_id', $responseIds)
                ->delete();

            $form->responses()->get()->each->delete();
            $form->invitations()->delete();

            FeedbackQuestionOption::query()
                ->whereIn('feedback_question_id', $questionIds)
                ->delete();

            $form->questions()->delete();
            $form->sections()->delete();
            $form->delete();
        });
    }
}
