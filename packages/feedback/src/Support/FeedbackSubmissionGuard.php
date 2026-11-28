<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Support;

use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Features\Forms\Api\SubmissionFormDefinition;
use Carbon\CarbonImmutable;
use RuntimeException;

final class FeedbackSubmissionGuard
{
    public function assertFormAcceptingSubmissions(
        SubmissionFormDefinition $form,
        bool $isAnonymous,
        ?string $respondentType,
        ?string $respondentId,
    ): void {
        if ($form->status !== FeedbackFormStatus::Published->value) {
            throw new RuntimeException('This form is not accepting submissions.');
        }

        if ($form->opensAt !== null && CarbonImmutable::now()->isBefore($form->opensAt)) {
            throw new RuntimeException('This form has not opened yet.');
        }

        if ($form->closesAt !== null && CarbonImmutable::now()->isAfter($form->closesAt)) {
            throw new RuntimeException('This form has closed.');
        }

        if ($isAnonymous && ! $form->isAnonymousAllowed) {
            throw new RuntimeException('Anonymous submissions are not allowed for this form.');
        }

        if ($form->isLoginRequired && ($respondentType === null || $respondentId === null)) {
            throw new RuntimeException('You must be logged in to submit this form.');
        }
    }
}
