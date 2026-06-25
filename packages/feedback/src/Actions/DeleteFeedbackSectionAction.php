<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackSection;
use Illuminate\Support\Facades\DB;

final class DeleteFeedbackSectionAction
{
    public function execute(FeedbackSection $section): void
    {
        $section = OwnerWriteGuard::findOrFailForOwner(FeedbackSection::class, $section->id);

        DB::transaction(function () use ($section): void {
            $section->questions()->update(['feedback_section_id' => null]);
            $section->delete();
        });
    }
}
