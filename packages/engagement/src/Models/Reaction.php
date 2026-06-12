<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Models;

use AIArmada\Engagement\Database\Factories\ReactionFactory;
use AIArmada\Engagement\Models\Concerns\UsesEngagementUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $reactor_type
 * @property string $reactor_id
 * @property string $reactable_type
 * @property string $reactable_id
 * @property string $reaction_type
 * @property string $status
 * @property \Carbon\CarbonImmutable|null $reacted_at
 * @property \Carbon\CarbonImmutable|null $removed_at
 * @property string|null $source
 * @property array|null $metadata
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
final class Reaction extends Model
{
    use HasFactory;
    use UsesEngagementUuid;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REMOVED = 'removed';

    protected $fillable = [
        'reactor_type',
        'reactor_id',
        'reactable_type',
        'reactable_id',
        'reaction_type',
        'status',
        'reacted_at',
        'removed_at',
        'source',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('engagement.database.tables.reactions', 'reactions');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reactor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param Builder<Reaction> $query
     * @return Builder<Reaction>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param Builder<Reaction> $query
     * @return Builder<Reaction>
     */
    public function scopeForReactor(Builder $query, Model $reactor): Builder
    {
        return $query->where('reactor_type', $reactor->getMorphClass())
            ->where('reactor_id', $reactor->getKey());
    }

    /**
     * @param Builder<Reaction> $query
     * @return Builder<Reaction>
     */
    public function scopeForReactable(Builder $query, Model $reactable): Builder
    {
        return $query->where('reactable_type', $reactable->getMorphClass())
            ->where('reactable_id', $reactable->getKey());
    }

    /**
     * @param Builder<Reaction> $query
     * @return Builder<Reaction>
     */
    public function scopeReactionType(Builder $query, string $type): Builder
    {
        return $query->where('reaction_type', $type);
    }

    public function isActive(): bool { return $this->status === self::STATUS_ACTIVE; }
    public function isRemoved(): bool { return $this->status === self::STATUS_REMOVED; }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'reacted_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): ReactionFactory
    {
        return ReactionFactory::new();
    }
}
