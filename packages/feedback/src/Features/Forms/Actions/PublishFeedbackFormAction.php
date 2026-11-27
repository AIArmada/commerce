<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Events\FeedbackFormPublished;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class PublishFeedbackFormAction
{
    public function execute(FeedbackForm $form): FeedbackForm
    {
        $guarded = OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id);

        return DB::transaction(function () use ($guarded): FeedbackForm {
            $form = FeedbackForm::query()->whereKey($guarded->id)->lockForUpdate()->firstOrFail();

            if ($form->status === FeedbackFormStatus::Published) {
                return $form;
            }

            $form->forceFill([
                'status' => FeedbackFormStatus::Published,
                'published_at' => CarbonImmutable::now(),
            ])->save();

            FeedbackFormPublished::dispatch($form);

            return $form;
        });
    }
}
