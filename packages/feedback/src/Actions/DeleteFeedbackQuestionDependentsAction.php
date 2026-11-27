<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Testimonials\Api\ManagesTestimonialDependencies;
use AIArmada\Feedback\Models\FeedbackQuestion;
use Illuminate\Support\Facades\DB;

final readonly class DeleteFeedbackQuestionDependentsAction
{
    public function __construct(private ManagesTestimonialDependencies $testimonials) {}

    public function execute(FeedbackQuestion $question): void
    {
        if (FeedbackQuestion::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(FeedbackQuestion::class, $question->id);
        }

        DB::transaction(function () use ($question): void {
            $this->testimonials->detachForQuestion($question->id);

            $question->options()->delete();
            $question->answers()->delete();
        });
    }
}
