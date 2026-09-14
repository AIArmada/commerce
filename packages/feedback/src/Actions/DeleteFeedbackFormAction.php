<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackQuestionOption;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class DeleteFeedbackFormAction
{
    public function execute(FeedbackForm $form): void
    {
        $form = OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id);

        DB::transaction(function () use ($form): void {
            $formId = $form->id;

            FeedbackTestimonial::query()
                ->where(function (Builder $query) use ($formId): void {
                    $query->whereHas('response', function (Builder $responseQuery) use ($formId): void {
                        $responseQuery->where('feedback_form_id', $formId);
                    })->orWhereHas('answer.response', function (Builder $responseQuery) use ($formId): void {
                        $responseQuery->where('feedback_form_id', $formId);
                    });
                })
                ->chunkById(200, fn ($testimonials): mixed => $testimonials->each->delete());

            FeedbackAnswer::query()
                ->whereHas('response', function (Builder $query) use ($formId): void {
                    $query->where('feedback_form_id', $formId);
                })
                ->delete();

            $form->responses()->chunkById(200, fn ($responses): mixed => $responses->each->delete());
            $form->invitations()->delete();

            FeedbackQuestionOption::query()
                ->whereHas('question', function (Builder $query) use ($formId): void {
                    $query->where('feedback_form_id', $formId);
                })
                ->delete();

            $form->questions()->delete();
            $form->sections()->delete();
            $form->delete();
        });
    }
}
