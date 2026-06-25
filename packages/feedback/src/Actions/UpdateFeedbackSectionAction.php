<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Models\FeedbackSection;

final class UpdateFeedbackSectionAction
{
    public function execute(FeedbackSection $section, array $data): FeedbackSection
    {
        $section = OwnerWriteGuard::findOrFailForOwner(FeedbackSection::class, $section->id);
        unset($data['feedback_form_id'], $data['owner_type'], $data['owner_id']);

        $section->fill($data)->save();

        return $section;
    }
}
