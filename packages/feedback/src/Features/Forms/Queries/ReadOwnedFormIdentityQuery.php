<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Queries;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Forms\Api\OwnedFormIdentity;
use AIArmada\Feedback\Features\Forms\Api\QuestionDefinition;
use AIArmada\Feedback\Features\Forms\Api\ReadsOwnedFormIdentity;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestion;

final readonly class ReadOwnedFormIdentityQuery implements ReadsOwnedFormIdentity
{
    public function find(string $formId): ?OwnedFormIdentity
    {
        $form = FeedbackForm::query()->whereKey($formId)->first();

        return $form === null ? null : $this->identity($form);
    }

    public function requireOwned(string $formId, bool $lock = false): OwnedFormIdentity
    {
        if (FeedbackForm::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $formId);
        }

        $query = FeedbackForm::query()->whereKey($formId);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $this->identity($query->firstOrFail());
    }

    public function requireOwnedQuestion(string $questionId): void
    {
        if (FeedbackQuestion::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(FeedbackQuestion::class, $questionId);

            return;
        }

        FeedbackQuestion::query()->whereKey($questionId)->firstOrFail();
    }

    public function questionDefinitions(string $formId, array $questionIds): array
    {
        $definitions = [];

        foreach (array_chunk($questionIds, 200) as $ids) {
            $questions = FeedbackQuestion::query()
                ->where('feedback_form_id', $formId)
                ->whereIn('id', $ids)
                ->with('options')
                ->get();

            foreach ($questions as $question) {
                $definitions[$question->id] = $question->definition();
            }
        }

        return $definitions;
    }

    private function identity(FeedbackForm $form): OwnedFormIdentity
    {
        return new OwnedFormIdentity($form->id, $form->owner_type, $form->owner_id, $form->name, $form->purpose);
    }
}
