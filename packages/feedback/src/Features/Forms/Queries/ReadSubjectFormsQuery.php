<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Queries;

use AIArmada\Feedback\Features\Forms\Api\ReadsSubjectForms;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;

final readonly class ReadSubjectFormsQuery implements ReadsSubjectForms
{
    public function ids(string $type, string $id): array
    {
        return FeedbackForm::query()->where('subject_type', $type)->where('subject_id', $id)
            ->orderBy('id')->pluck('id')->all();
    }
}
