<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Enums\EventEngagementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $event_id
 * @property string|null $occurrence_id
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property EventEngagementType $type
 * @property int $weight
 * @property array<string, mixed>|null $metadata
 */
class EventEngagement extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'event_id',
        'occurrence_id',
        'actor_type',
        'actor_id',
        'type',
        'weight',
        'metadata',
    ];

    protected $attributes = [
        'weight' => 1,
    ];

    protected function casts(): array
    {
        return [
            'type' => EventEngagementType::class,
            'weight' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('events.database.tables.engagements', 'event_engagements');
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Occurrence, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(Occurrence::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'actor_type', 'actor_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithType(Builder $query, array | string | EventEngagementType $types): Builder
    {
        $normalizedTypes = array_values(array_filter(
            array_map(static function (EventEngagementType | string $type): string {
                return $type instanceof EventEngagementType ? $type->value : $type;
            }, Arr::wrap($types)),
            static fn (string $type): bool => mb_trim($type) !== '',
        ));

        return $query->whereIn($this->qualifyColumn('type'), $normalizedTypes);
    }

    public function isType(EventEngagementType $type): bool
    {
        return $this->resolvedType() === $type;
    }

    public function isSaved(): bool
    {
        return $this->isType(EventEngagementType::Saved);
    }

    public function isGoing(): bool
    {
        return $this->isType(EventEngagementType::Going);
    }

    public function isInterested(): bool
    {
        return $this->isType(EventEngagementType::Interested);
    }

    public function typeLabel(): string
    {
        return $this->resolvedType()->label();
    }

    public function typeColor(): string
    {
        return $this->resolvedType()->color();
    }

    private function resolvedType(): EventEngagementType
    {
        if ($this->type instanceof EventEngagementType) {
            return $this->type;
        }

        if (is_string($this->type) && EventEngagementType::tryFrom($this->type) instanceof EventEngagementType) {
            return EventEngagementType::from($this->type);
        }

        return EventEngagementType::Saved;
    }
}
