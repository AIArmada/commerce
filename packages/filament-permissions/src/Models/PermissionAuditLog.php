<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Models;

use AIArmada\FilamentPermissions\Enums\AuditEventType;
use AIArmada\FilamentPermissions\Enums\AuditSeverity;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $event_type
 * @property string $severity
 * @property string $actor_type
 * @property string $actor_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $target_type
 * @property string|null $target_id
 * @property string|null $target_name
 * @property array<string, mixed>|null $old_value
 * @property array<string, mixed>|null $new_value
 * @property array<string, mixed>|null $context
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $session_id
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model $actor
 * @property-read Model|null $subject
 * @property-read Model|null $target
 */
class PermissionAuditLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'event_type',
        'severity',
        'actor_type',
        'actor_id',
        'subject_type',
        'subject_id',
        'target_type',
        'target_id',
        'target_name',
        'old_value',
        'new_value',
        'context',
        'ip_address',
        'user_agent',
        'session_id',
        'occurred_at',
    ];

    public function getTable(): string
    {
        /** @var string $table */
        $table = config('filament-permissions.database.tables.audit_logs', 'perm_audit_logs');

        return $table;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the event type as enum.
     */
    public function getEventTypeEnum(): ?AuditEventType
    {
        return AuditEventType::tryFrom($this->event_type);
    }

    /**
     * Get the severity as enum.
     */
    public function getSeverityEnum(): AuditSeverity
    {
        return AuditSeverity::tryFrom($this->severity) ?? AuditSeverity::Low;
    }

    /**
     * Get a human-readable description of the event.
     */
    public function getDescription(): string
    {
        $eventType = $this->getEventTypeEnum();

        if ($eventType === null) {
            return $this->event_type;
        }

        $description = $eventType->label();

        if ($this->target_name !== null) {
            $description .= ': '.$this->target_name;
        }

        return $description;
    }

    /**
     * Get the changes between old and new values.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function getChanges(): array
    {
        $oldValue = $this->old_value ?? [];
        $newValue = $this->new_value ?? [];

        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($oldValue), array_keys($newValue)));

        foreach ($allKeys as $key) {
            $old = $oldValue[$key] ?? null;
            $new = $newValue[$key] ?? null;

            if ($old !== $new) {
                $changes[$key] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }

    /**
     * Check if this is a high-severity event.
     */
    public function isHighSeverity(): bool
    {
        $severity = $this->getSeverityEnum();

        return $severity === AuditSeverity::High || $severity === AuditSeverity::Critical;
    }

    /**
     * Scope to filter by event type.
     *
     * @param  Builder<PermissionAuditLog>  $query
     * @return Builder<PermissionAuditLog>
     */
    public function scopeOfEventType(Builder $query, AuditEventType|string $eventType): Builder
    {
        $type = $eventType instanceof AuditEventType ? $eventType->value : $eventType;

        return $query->where('event_type', $type);
    }

    /**
     * Scope to filter by severity.
     *
     * @param  Builder<PermissionAuditLog>  $query
     * @return Builder<PermissionAuditLog>
     */
    public function scopeOfSeverity(Builder $query, AuditSeverity|string $severity): Builder
    {
        $sev = $severity instanceof AuditSeverity ? $severity->value : $severity;

        return $query->where('severity', $sev);
    }

    /**
     * Scope to filter by minimum severity.
     *
     * @param  Builder<PermissionAuditLog>  $query
     * @return Builder<PermissionAuditLog>
     */
    public function scopeMinimumSeverity(Builder $query, AuditSeverity $severity): Builder
    {
        $levels = collect(AuditSeverity::cases())
            ->filter(fn (AuditSeverity $s) => $s->numericLevel() >= $severity->numericLevel())
            ->map(fn (AuditSeverity $s) => $s->value)
            ->toArray();

        return $query->whereIn('severity', $levels);
    }

    /**
     * Scope to filter by actor.
     *
     * @param  Builder<PermissionAuditLog>  $query
     * @return Builder<PermissionAuditLog>
     */
    public function scopeByActor(Builder $query, Model $actor): Builder
    {
        return $query
            ->where('actor_type', $actor->getMorphClass())
            ->where('actor_id', $actor->getKey());
    }

    /**
     * Scope to filter by subject.
     *
     * @param  Builder<PermissionAuditLog>  $query
     * @return Builder<PermissionAuditLog>
     */
    public function scopeBySubject(Builder $query, Model $subject): Builder
    {
        return $query
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    /**
     * Scope to filter by date range.
     *
     * @param  Builder<PermissionAuditLog>  $query
     * @return Builder<PermissionAuditLog>
     */
    public function scopeBetweenDates(Builder $query, DateTimeInterface $start, DateTimeInterface $end): Builder
    {
        return $query
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<=', $end);
    }

    /**
     * Scope to filter by event category.
     *
     * @param  Builder<PermissionAuditLog>  $query
     * @return Builder<PermissionAuditLog>
     */
    public function scopeOfCategory(Builder $query, string $category): Builder
    {
        $eventTypes = collect(AuditEventType::cases())
            ->filter(fn (AuditEventType $e) => $e->category() === $category)
            ->map(fn (AuditEventType $e) => $e->value)
            ->toArray();

        return $query->whereIn('event_type', $eventTypes);
    }

    /**
     * Scope to filter recent events.
     *
     * @param  Builder<PermissionAuditLog>  $query
     * @return Builder<PermissionAuditLog>
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('occurred_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope to filter by session.
     *
     * @param  Builder<PermissionAuditLog>  $query
     * @return Builder<PermissionAuditLog>
     */
    public function scopeForSession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
