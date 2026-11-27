<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Testimonials\Api\ManagesTestimonialDependencies;
use AIArmada\Feedback\Features\Analytics\Api\DeletesFormAnalytics;
use AIArmada\Feedback\Features\Invitations\Api\ManagesInvitationLifecycle;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackQuestionOption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class DeleteFeedbackFormDependentsAction
{
    public function __construct(
        private DeletesFormAnalytics $deleteAnalytics,
        private ManagesInvitationLifecycle $invitations,
        private ManagesTestimonialDependencies $testimonials,
    ) {}

    public function execute(FeedbackForm $form): void
    {
        if (FeedbackForm::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id);
        }

        DB::transaction(function () use ($form): void {
            $formId = $form->id;

            $this->testimonials->deleteForForm($formId);

            FeedbackAnswer::query()
                ->whereHas('response', fn (Builder $query) => $query->where('feedback_form_id', $formId))
                ->delete();

            $form->responses()->delete();
            $this->invitations->deleteForForm($formId);

            FeedbackQuestionOption::query()
                ->whereHas('question', fn (Builder $query) => $query->where('feedback_form_id', $formId))
                ->delete();

            $form->questions()->delete();
            $form->sections()->delete();
            $this->deleteAnalytics->execute($formId);
        });
    }
}
