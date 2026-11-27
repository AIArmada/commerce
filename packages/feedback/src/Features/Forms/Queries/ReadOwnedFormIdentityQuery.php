<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Queries;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Forms\Api\OwnedFormIdentity;
use AIArmada\Feedback\Features\Forms\Api\ReadsOwnedFormIdentity;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;

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

    private function identity(FeedbackForm $form): OwnedFormIdentity
    {
        return new OwnedFormIdentity($form->id, $form->owner_type, $form->owner_id);
    }
}
