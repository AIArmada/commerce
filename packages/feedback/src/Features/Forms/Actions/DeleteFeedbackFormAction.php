<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use Illuminate\Support\Facades\DB;

final readonly class DeleteFeedbackFormAction
{
    public function execute(FeedbackForm $form): void
    {
        $guarded = OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id);

        DB::transaction(function () use ($guarded): void {
            FeedbackForm::query()->whereKey($guarded->id)->lockForUpdate()->firstOrFail()->delete();
        });
    }
}
