<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestion;
use AIArmada\Feedback\Features\Responses\Api\DeletesResponseDependencies;

final readonly class DeleteFeedbackQuestionDependentsAction
{
    public function __construct(
        private DeletesResponseDependencies $responses,
    ) {}

    public function execute(FeedbackQuestion $question): void
    {
        if (FeedbackQuestion::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(FeedbackQuestion::class, $question->id);
        }

        $question->getConnection()->transaction(function () use ($question): void {
            $question->options()->delete();
            $this->responses->deleteAnswersForQuestion($question->id);
        });
    }
}
