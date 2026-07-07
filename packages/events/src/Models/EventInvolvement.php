<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventInvolvementFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string|null $involveable_type
 * @property string|null $involveable_id
 * @property string|null $event_role_id
 * @property string|null $role_code
 * @property string $status
 * @property string $visibility
 * @property int $prominence
 * @property bool $is_featured
 * @property bool $is_primary
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property string|null $replaced_by_involvement_id
 * @property string|null $replacement_reason
 * @property string|null $notes
 * @property int $sort_order
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read EventOccurrence|null $occurrence
 * @property-read EventSession|null $session
 * @property-read EventRole|null $role
 * @property-read Model|Eloquent $involveable
 * @property-read EventInvolvement|null $replacedByInvolvement
 * @property-read Collection<int, EventInvolvement> $replacementFor
 */
class EventInvolvement extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'involveable_type', 'involveable_id',
        'event_role_id', 'role_code',
        'status', 'visibility',
        'prominence', 'is_featured', 'is_primary',
        'starts_at', 'ends_at',
        'replaced_by_involvement_id', 'replacement_reason',
        'notes', 'sort_order',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_involvements', 'event_involvements');
    }

    protected function casts(): array
    {
        return [
            'prominence' => 'integer',
            'is_featured' => 'boolean',
            'is_primary' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<EventOccurrence, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<EventSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    /**
     * @return BelongsTo<EventRole, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(EventRole::class, 'event_role_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function involveable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<EventInvolvement, $this>
     */
    public function replacedByInvolvement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_involvement_id');
    }

    /**
     * @return HasMany<EventInvolvement, $this>
     */
    public function replacementFor(): HasMany
    {
        return $this->hasMany(self::class, 'replaced_by_involvement_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRole(Builder $query, string $roleCode): Builder
    {
        return $query->where('role_code', $roleCode);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', 'public');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeHeadliner(Builder $query): Builder
    {
        return $query->where('is_primary', true)->orWhere('prominence', '>=', 100);
    }

    protected static function newFactory(): EventInvolvementFactory
    {
        return EventInvolvementFactory::new();
    }
}
