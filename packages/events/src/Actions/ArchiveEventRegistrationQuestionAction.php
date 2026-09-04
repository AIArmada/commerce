<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Enums\EventRegistrationQuestionStatus;
use AIArmada\Events\Events\EventRegistrationQuestionArchived;
use AIArmada\Events\Models\EventRegistrationQuestion;
use AIArmada\Events\Support\EventWriteGuard;
use Carbon\CarbonImmutable;

final class ArchiveEventRegistrationQuestionAction
{
    public function handle(EventRegistrationQuestion $question): EventRegistrationQuestion
    {
        EventWriteGuard::findOrFail($question->event_id);

        if ($question->status === EventRegistrationQuestionStatus::Archived) {
            return $question->fresh();
        }

        $question->forceFill([
            'status' => EventRegistrationQuestionStatus::Archived->value,
            'archived_at' => CarbonImmutable::now(),
        ])->save();

        event(new EventRegistrationQuestionArchived($question));

        return $question->fresh();
    }
}
