<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Models\FeedbackSection;

final class UpdateFeedbackSectionAction
{
    public function execute(FeedbackSection $section, array $data): FeedbackSection
    {
        $section->fill($data)->save();

        return $section;
    }
}
