<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Forms\Models\FeedbackSection;
use Illuminate\Support\Facades\DB;

final readonly class DeleteFeedbackSectionDependentsAction
{
    public function execute(FeedbackSection $section): void
    {
        OwnerWriteGuard::findOrFailForOwner(FeedbackSection::class, $section->id);

        DB::transaction(function () use ($section): void {
            $section->questions()->update(['feedback_section_id' => null]);
        });
    }
}
