<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Actions;

use AIArmada\Events\Data\EventRegistrationQuestionStatus;
use AIArmada\Events\Events\EventRegistrationQuestionArchived;
use AIArmada\Events\Features\Registrations\Models\EventRegistrationQuestion;
use AIArmada\Events\Platform\EventWriteGuard;
use Carbon\CarbonImmutable;

final class ArchiveEventRegistrationQuestionAction
{
    public function handle(string $questionId): void
    {
        /** @var EventRegistrationQuestion $question */
        $question = EventRegistrationQuestion::query()->whereKey($questionId)->firstOrFail();

        EventWriteGuard::findOrFail((string) $question->event_id);

        if ($question->status === EventRegistrationQuestionStatus::Archived) {
            return;
        }

        $question->forceFill([
            'status' => EventRegistrationQuestionStatus::Archived->value,
            'archived_at' => CarbonImmutable::now(),
        ])->save();

        event(new EventRegistrationQuestionArchived(
            (string) $question->getKey(),
            (string) $question->event_id,
            $question->event_occurrence_id !== null ? (string) $question->event_occurrence_id : null,
            $question->event_session_id !== null ? (string) $question->event_session_id : null,
        ));
    }
}
