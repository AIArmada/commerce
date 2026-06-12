<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\EventChangeLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

trait RecordsEventChanges
{
    public function recordEventChange(string $changeType, array $oldValue = [], array $newValue = [], array $context = []): EventChangeLog
    {
        $metadata = Arr::except($context, [
            'event_id',
            'event_occurrence_id',
            'event_session_id',
            'subject_type',
            'subject_id',
            'change_category',
            'impact_level',
            'visibility',
            'requires_notification',
            'changed_at',
            'reason',
            'internal_notes',
        ]);

        return EventChangeLog::query()->create([
            'event_id' => $context['event_id'] ?? $this->getKey(),
            'event_occurrence_id' => $context['event_occurrence_id'] ?? null,
            'event_session_id' => $context['event_session_id'] ?? null,
            'subject_type' => $context['subject_type'] ?? $this->getMorphClass(),
            'subject_id' => $context['subject_id'] ?? $this->getKey(),
            'change_type' => $changeType,
            'change_category' => $context['change_category'] ?? 'administration',
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $context['reason'] ?? null,
            'internal_notes' => $context['internal_notes'] ?? null,
            'impact_level' => $context['impact_level'] ?? 'low',
            'visibility' => $context['visibility'] ?? 'internal',
            'requires_notification' => (bool) ($context['requires_notification'] ?? false),
            'changed_at' => $context['changed_at'] ?? CarbonImmutable::now(),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
