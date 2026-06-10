<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Models\EventChange;
use Spatie\LaravelData\Data;

final class EventChangeNoticePayloadData extends Data
{
    /**
     * @param  array<string, mixed>|null  $changedSections
     * @param  array<string, mixed>|null  $beforeSnapshot
     * @param  array<string, mixed>|null  $afterSnapshot
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $eventId,
        public readonly ?string $replacementEventId = null,
        public readonly ?string $replacementOccurrenceId = null,
        public readonly string $changeKey = 'content_changed',
        public readonly string $severity = 'info',
        public readonly string $status = 'draft',
        public readonly ?array $changedSections = null,
        public readonly ?array $beforeSnapshot = null,
        public readonly ?array $afterSnapshot = null,
        public readonly ?string $publishedAt = null,
        public readonly ?string $retractedAt = null,
        public readonly ?array $metadata = null,
    ) {}

    public static function fromNotice(EventChange $notice): self
    {
        return new self(
            id: $notice->id,
            eventId: $notice->event_id,
            replacementEventId: $notice->replacement_event_id,
            replacementOccurrenceId: $notice->replacement_occurrence_id,
            changeKey: $notice->change_key,
            severity: $notice->severity,
            status: $notice->status,
            changedSections: $notice->changed_sections,
            beforeSnapshot: $notice->before_snapshot,
            afterSnapshot: $notice->after_snapshot,
            publishedAt: $notice->published_at?->toISOString(),
            retractedAt: $notice->retracted_at?->toISOString(),
            metadata: $notice->metadata,
        );
    }
}
