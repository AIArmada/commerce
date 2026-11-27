<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Models\FeedbackForm;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class ArchiveFeedbackFormAction
{
    public function execute(FeedbackForm $form): FeedbackForm
    {
        $guarded = OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id);

        return DB::transaction(function () use ($guarded): FeedbackForm {
            $form = FeedbackForm::query()->whereKey($guarded->id)->lockForUpdate()->firstOrFail();

            if ($form->status === FeedbackFormStatus::Archived) {
                return $form;
            }

            $form->forceFill([
                'status' => FeedbackFormStatus::Archived,
                'archived_at' => CarbonImmutable::now(),
            ])->save();

            return $form;
        });
    }
}
