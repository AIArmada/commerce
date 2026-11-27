<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Analytics\Api\DeletesFormAnalytics;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestionOption;
use AIArmada\Feedback\Features\Invitations\Api\ManagesInvitationLifecycle;
use AIArmada\Feedback\Features\Responses\Api\DeletesResponseDependencies;
use AIArmada\Feedback\Features\Testimonials\Api\ManagesTestimonialDependencies;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class DeleteFeedbackFormDependentsAction
{
    public function __construct(
        private DeletesFormAnalytics $deleteAnalytics,
        private ManagesInvitationLifecycle $invitations,
        private ManagesTestimonialDependencies $testimonials,
        private DeletesResponseDependencies $responses,
    ) {}

    public function execute(FeedbackForm $form): void
    {
        if (FeedbackForm::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id);
        }

        DB::transaction(function () use ($form): void {
            $formId = $form->id;

            $this->testimonials->deleteForForm($formId);

            $this->responses->deleteForForm($formId);
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
