<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Events\FeedbackFormClosed;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class CloseFeedbackFormAction
{
    public function execute(FeedbackForm $form): FeedbackForm
    {
        $guarded = OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id);

        return DB::transaction(function () use ($guarded): FeedbackForm {
            $form = FeedbackForm::query()->whereKey($guarded->id)->lockForUpdate()->firstOrFail();

            if ($form->status === FeedbackFormStatus::Closed) {
                return $form;
            }

            $form->forceFill([
                'status' => FeedbackFormStatus::Closed,
                'closed_at' => CarbonImmutable::now(),
            ])->save();

            FeedbackFormClosed::dispatch($form->id, $form->owner_type, $form->owner_id);

            return $form;
        });
    }
}
