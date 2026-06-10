<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Contracts\EventChangeNoticeAudienceResolver;
use AIArmada\Events\Data\EventChangeNoticeAudienceData;
use AIArmada\Events\Events\EventChangeNoticePublished;
use AIArmada\Events\Events\EventChangeNoticeRetracted;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $event_id
 * @property string|null $replacement_event_id
 * @property string|null $replacement_occurrence_id
 * @property string $change_key
 * @property string $severity
 * @property string $status
 * @property array<string, mixed>|null $changed_sections
 * @property array<string, mixed>|null $before_snapshot
 * @property array<string, mixed>|null $after_snapshot
 * @property Carbon|null $published_at
 * @property Carbon|null $retracted_at
 * @property array<string, mixed>|null $metadata
 */
class EventChange extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'event_id',
        'replacement_event_id',
        'replacement_occurrence_id',
        'change_key',
        'severity',
        'status',
        'changed_sections',
        'before_snapshot',
        'after_snapshot',
        'published_at',
        'retracted_at',
        'metadata',
    ];

    protected $attributes = [
        'severity' => 'info',
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'changed_sections' => 'array',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'published_at' => 'immutable_datetime',
            'retracted_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $notice): void {
            $notice->assertReplacementLinksAreValid();
        });

        static::updated(static function (self $notice): void {
            if (! $notice->wasChanged('status')) {
                return;
            }

            $notice->dispatchStateTransitionEvent();
        });
    }

    public function getTable(): string
    {
        return config('events.database.tables.change_notices', 'event_change_notices');
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function replacementEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'replacement_event_id');
    }

    /**
     * @return BelongsTo<Occurrence, $this>
     */
    public function replacementOccurrence(): BelongsTo
    {
        return $this->belongsTo(Occurrence::class, 'replacement_occurrence_id');
    }

    public function publish(): void
    {
        $this->status = 'published';
        $this->published_at ??= now();
        $this->retracted_at = null;
    }

    public function retract(): void
    {
        $this->status = 'retracted';
        $this->retracted_at ??= now();
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function audiences(): EventChangeNoticeAudienceData
    {
        return app(EventChangeNoticeAudienceResolver::class)->resolve($this);
    }

    /**
     * @param  string|array<int, string>  $changeKeys
     */
    public function changeKeyIs(string | array $changeKeys): bool
    {
        $values = is_array($changeKeys) ? $changeKeys : [$changeKeys];

        return in_array($this->change_key, $values, true);
    }

    public function isSpeakerChange(): bool
    {
        return $this->changeKeyIs(['speaker_changed', 'people_changed']);
    }

    public function isTitleChange(): bool
    {
        return $this->changeKeyIs('title_changed');
    }

    public function isTopicChange(): bool
    {
        return $this->changeKeyIs('topic_changed');
    }

    public function isContentChange(): bool
    {
        return $this->changeKeyIs(['content_changed', 'title_changed', 'topic_changed']);
    }

    public function isScheduleChange(): bool
    {
        return $this->changeKeyIs(['schedule_changed', 'cancelled', 'postponed']);
    }

    public function isCancellation(): bool
    {
        return $this->changeKeyIs('cancelled');
    }

    public function isPostponement(): bool
    {
        return $this->changeKeyIs('postponed');
    }

    public function isReplacementLink(): bool
    {
        return $this->changeKeyIs('replacement_linked');
    }

    public function hasCompoundChanges(): bool
    {
        $sections = $this->changed_sections ?? [];

        if ($sections === []) {
            return false;
        }

        $activeSections = array_filter(
            $sections,
            static fn (mixed $value): bool => $value !== null && $value !== false && $value !== [],
        );

        return count($activeSections) > 1;
    }

    private function assertReplacementLinksAreValid(): void
    {
        $replacementEventId = $this->normalizedIdentifier($this->replacement_event_id);

        if ($replacementEventId === null) {
            return;
        }

        $eventId = $this->normalizedIdentifier($this->event_id);

        if ($eventId !== null && $replacementEventId === $eventId) {
            throw new InvalidArgumentException('A change notice cannot replace its own event.');
        }

        $this->runWithinOwnerContext(function () use ($eventId, $replacementEventId): void {
            $visited = $eventId !== null ? [$eventId => true] : [];
            $queue = [$replacementEventId];

            while ($queue !== []) {
                $currentEventId = array_shift($queue);

                if (! is_string($currentEventId) || mb_trim($currentEventId) === '') {
                    continue;
                }

                $currentEventId = mb_trim($currentEventId);

                if (isset($visited[$currentEventId])) {
                    throw new InvalidArgumentException('Replacement event links cannot form a cycle.');
                }

                $visited[$currentEventId] = true;

                $nextEventIds = static::query()
                    ->where('event_id', $currentEventId)
                    ->pluck('replacement_event_id')
                    ->filter(static fn (mixed $value): bool => is_string($value) && mb_trim($value) !== '')
                    ->map(static fn (mixed $value): string => mb_trim((string) $value))
                    ->all();

                foreach ($nextEventIds as $nextEventId) {
                    if (isset($visited[$nextEventId])) {
                        throw new InvalidArgumentException('Replacement event links cannot form a cycle.');
                    }

                    $queue[] = $nextEventId;
                }
            }
        });
    }

    private function dispatchStateTransitionEvent(): void
    {
        $domainEvent = match ($this->status) {
            'published' => new EventChangeNoticePublished($this->fresh() ?? $this),
            'retracted' => new EventChangeNoticeRetracted($this->fresh() ?? $this),
            default => null,
        };

        if ($domainEvent === null) {
            return;
        }

        $this->dispatchAfterCommit($domainEvent);
    }

    private function dispatchAfterCommit(object $domainEvent): void
    {
        if (DB::transactionLevel() > 0 && function_exists('app') && ! app()->runningUnitTests()) {
            DB::afterCommit(static fn () => event($domainEvent));

            return;
        }

        event($domainEvent);
    }

    private function runWithinOwnerContext(callable $callback): mixed
    {
        $owner = $this->exists
            ? OwnerContext::fromTypeAndId(
                is_string($this->getAttribute('owner_type')) ? $this->getAttribute('owner_type') : null,
                is_scalar($this->getAttribute('owner_id')) ? (string) $this->getAttribute('owner_id') : null,
            )
            : OwnerContext::resolve();

        return OwnerContext::withOwner($owner, $callback);
    }

    private function normalizedIdentifier(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value !== '' ? $value : null;
    }
}
