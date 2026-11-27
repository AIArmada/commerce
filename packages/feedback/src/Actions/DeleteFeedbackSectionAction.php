<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackSection;
use Illuminate\Support\Facades\DB;

final readonly class DeleteFeedbackSectionAction
{
    public function execute(FeedbackSection $section): void
    {
        $guarded = OwnerWriteGuard::findOrFailForOwner(FeedbackSection::class, $section->id);

        DB::transaction(function () use ($guarded): void {
            FeedbackSection::query()->whereKey($guarded->id)->lockForUpdate()->firstOrFail()->delete();
        });
    }
}
