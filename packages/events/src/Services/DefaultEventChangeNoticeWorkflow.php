<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventChangeNoticeWorkflow;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventChangeNotice;
use AIArmada\Events\Models\Occurrence;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DefaultEventChangeNoticeWorkflow implements EventChangeNoticeWorkflow
{
    public function create(
        Event $event,
        string $changeKey,
        array $changedSections = [],
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?string $severity = null,
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
    ): EventChangeNotice {
        return $this->withEventOwnerContext($event, function () use (
            $event,
            $changeKey,
            $changedSections,
            $beforeSnapshot,
            $afterSnapshot,
            $metadata,
            $severity,
            $replacementEvent,
            $replacementOccurrence
        ): EventChangeNotice {
            $this->assertReplacementOwnership($event, $replacementEvent, $replacementOccurrence);

            $notice = new EventChangeNotice;
            $notice->event()->associate($event);
            $notice->fill([
                'change_key' => $changeKey,
                'severity' => $severity ?? $this->defaultSeverityForChangeKey($changeKey),
                'state' => 'draft',
                'changed_sections' => $this->normalizeNullableArray($changedSections),
                'before_snapshot' => $this->normalizeNullableArray($beforeSnapshot),
                'after_snapshot' => $this->normalizeNullableArray($afterSnapshot),
                'metadata' => $this->normalizeNoticeMetadata($metadata),
            ]);

            if ($replacementEvent instanceof Event) {
                $notice->replacementEvent()->associate($replacementEvent);
            }

            if ($replacementOccurrence instanceof Occurrence) {
                $notice->replacementOccurrence()->associate($replacementOccurrence);
            }

            $notice->save();

            return $notice->fresh() ?? $notice;
        });
    }

    public function bundle(
        Event $event,
        array $changedSections,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        string $changeKey = 'content_changed',
        ?string $severity = null,
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
    ): EventChangeNotice {
        return $this->create(
            event: $event,
            changeKey: $changeKey,
            changedSections: $changedSections,
            beforeSnapshot: $beforeSnapshot,
            afterSnapshot: $afterSnapshot,
            metadata: $metadata,
            severity: $severity,
            replacementEvent: $replacementEvent,
            replacementOccurrence: $replacementOccurrence,
        );
    }

    public function speakerChanged(
        Event $event,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?string $severity = null,
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
    ): EventChangeNotice {
        return $this->create(
            event: $event,
            changeKey: 'speaker_changed',
            changedSections: ['people' => ['speaker' => true]],
            beforeSnapshot: $beforeSnapshot,
            afterSnapshot: $afterSnapshot,
            metadata: $metadata,
            severity: $severity,
            replacementEvent: $replacementEvent,
            replacementOccurrence: $replacementOccurrence,
        );
    }

    public function titleChanged(
        Event $event,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?string $severity = null,
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
    ): EventChangeNotice {
        return $this->create(
            event: $event,
            changeKey: 'title_changed',
            changedSections: ['title' => true],
            beforeSnapshot: $beforeSnapshot,
            afterSnapshot: $afterSnapshot,
            metadata: $metadata,
            severity: $severity,
            replacementEvent: $replacementEvent,
            replacementOccurrence: $replacementOccurrence,
        );
    }

    public function topicChanged(
        Event $event,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?string $severity = null,
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
    ): EventChangeNotice {
        return $this->create(
            event: $event,
            changeKey: 'topic_changed',
            changedSections: ['topic' => true],
            beforeSnapshot: $beforeSnapshot,
            afterSnapshot: $afterSnapshot,
            metadata: $metadata,
            severity: $severity,
            replacementEvent: $replacementEvent,
            replacementOccurrence: $replacementOccurrence,
        );
    }

    public function contentChanged(
        Event $event,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?string $severity = null,
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
    ): EventChangeNotice {
        return $this->create(
            event: $event,
            changeKey: 'content_changed',
            changedSections: ['content' => true],
            beforeSnapshot: $beforeSnapshot,
            afterSnapshot: $afterSnapshot,
            metadata: $metadata,
            severity: $severity,
            replacementEvent: $replacementEvent,
            replacementOccurrence: $replacementOccurrence,
        );
    }

    public function scheduleChanged(
        Event $event,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?string $severity = null,
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
    ): EventChangeNotice {
        return $this->create(
            event: $event,
            changeKey: 'schedule_changed',
            changedSections: ['schedule' => true],
            beforeSnapshot: $beforeSnapshot,
            afterSnapshot: $afterSnapshot,
            metadata: $metadata,
            severity: $severity,
            replacementEvent: $replacementEvent,
            replacementOccurrence: $replacementOccurrence,
        );
    }

    public function cancelled(
        Event $event,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
        ?string $severity = null,
    ): EventChangeNotice {
        return $this->create(
            event: $event,
            changeKey: 'cancelled',
            changedSections: [
                'status' => ['cancelled' => true],
                'schedule' => ['cancelled' => true],
            ],
            beforeSnapshot: $beforeSnapshot,
            afterSnapshot: $afterSnapshot,
            metadata: $metadata,
            severity: $severity ?? 'urgent',
            replacementEvent: $replacementEvent,
            replacementOccurrence: $replacementOccurrence,
        );
    }

    public function postponed(
        Event $event,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
        ?string $severity = null,
    ): EventChangeNotice {
        return $this->create(
            event: $event,
            changeKey: 'postponed',
            changedSections: ['schedule' => ['postponed' => true]],
            beforeSnapshot: $beforeSnapshot,
            afterSnapshot: $afterSnapshot,
            metadata: $metadata,
            severity: $severity ?? 'high',
            replacementEvent: $replacementEvent,
            replacementOccurrence: $replacementOccurrence,
        );
    }

    public function replacementLinked(
        Event $event,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        array $metadata = [],
        ?Event $replacementEvent = null,
        ?Occurrence $replacementOccurrence = null,
        ?string $severity = null,
    ): EventChangeNotice {
        return $this->create(
            event: $event,
            changeKey: 'replacement_linked',
            changedSections: ['replacement' => true],
            beforeSnapshot: $beforeSnapshot,
            afterSnapshot: $afterSnapshot,
            metadata: $metadata,
            severity: $severity,
            replacementEvent: $replacementEvent,
            replacementOccurrence: $replacementOccurrence,
        );
    }

    public function publish(EventChangeNotice $notice): EventChangeNotice
    {
        return $this->withNoticeOwnerContext($notice, function () use ($notice): EventChangeNotice {
            DB::transaction(function () use ($notice): void {
                $notice->publish();
                $notice->save();
            });

            return $notice->fresh() ?? $notice;
        });
    }

    public function retract(EventChangeNotice $notice): EventChangeNotice
    {
        return $this->withNoticeOwnerContext($notice, function () use ($notice): EventChangeNotice {
            DB::transaction(function () use ($notice): void {
                $notice->retract();
                $notice->save();
            });

            return $notice->fresh() ?? $notice;
        });
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function withEventOwnerContext(Event $event, callable $callback): mixed
    {
        $owner = OwnerContext::fromTypeAndId(
            is_string($event->getAttribute('owner_type')) ? $event->getAttribute('owner_type') : null,
            is_scalar($event->getAttribute('owner_id')) ? (string) $event->getAttribute('owner_id') : null,
        );

        return OwnerContext::withOwner($owner, $callback);
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function withNoticeOwnerContext(EventChangeNotice $notice, callable $callback): mixed
    {
        $owner = OwnerContext::fromTypeAndId(
            is_string($notice->getAttribute('owner_type')) ? $notice->getAttribute('owner_type') : null,
            is_scalar($notice->getAttribute('owner_id')) ? (string) $notice->getAttribute('owner_id') : null,
        );

        return OwnerContext::withOwner($owner, $callback);
    }

    private function assertReplacementOwnership(
        Event $event,
        ?Event $replacementEvent,
        ?Occurrence $replacementOccurrence,
    ): void {
        $ownerType = is_string($event->getAttribute('owner_type')) ? $event->getAttribute('owner_type') : null;
        $ownerId = is_scalar($event->getAttribute('owner_id')) ? (string) $event->getAttribute('owner_id') : null;

        if ($replacementEvent instanceof Event) {
            $replacementOwnerType = is_string($replacementEvent->getAttribute('owner_type')) ? $replacementEvent->getAttribute('owner_type') : null;
            $replacementOwnerId = is_scalar($replacementEvent->getAttribute('owner_id')) ? (string) $replacementEvent->getAttribute('owner_id') : null;

            if ($ownerType !== $replacementOwnerType || $ownerId !== $replacementOwnerId) {
                throw new InvalidArgumentException('Replacement events must share the same owner context as the notice event.');
            }
        }

        if ($replacementOccurrence instanceof Occurrence) {
            $replacementOwnerType = is_string($replacementOccurrence->getAttribute('owner_type')) ? $replacementOccurrence->getAttribute('owner_type') : null;
            $replacementOwnerId = is_scalar($replacementOccurrence->getAttribute('owner_id')) ? (string) $replacementOccurrence->getAttribute('owner_id') : null;

            if ($ownerType !== $replacementOwnerType || $ownerId !== $replacementOwnerId) {
                throw new InvalidArgumentException('Replacement occurrences must share the same owner context as the notice event.');
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $value
     * @return array<string, mixed>|null
     */
    private function normalizeNullableArray(?array $value): ?array
    {
        if ($value === null || $value === []) {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>|null
     */
    private function normalizeNoticeMetadata(array $metadata): ?array
    {
        $normalized = Arr::where($metadata, static fn (mixed $value): bool => $value !== null);

        return $normalized === [] ? null : $normalized;
    }

    private function defaultSeverityForChangeKey(string $changeKey): string
    {
        return match ($changeKey) {
            'speaker_changed', 'title_changed', 'topic_changed', 'people_changed' => 'high',
            'cancelled' => 'urgent',
            'postponed' => 'high',
            default => 'info',
        };
    }
}
