<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Moderation\Enums\BlockReason;
use AIArmada\Moderation\Enums\BlockStatus;
use AIArmada\Moderation\Models\Concerns\UsesModerationUuid;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $blockable_type
 * @property string|null $blockable_id
 * @property string|null $blocked_by_type
 * @property string|null $blocked_by_id
 * @property BlockReason $reason
 * @property BlockStatus $status
 * @property string|null $notes
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $lifted_at
 * @property string|null $lifted_by_type
 * @property string|null $lifted_by_id
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $blockable
 * @property-read Model|Eloquent $blockedBy
 * @property-read Model|Eloquent $liftedBy
 */
final class Block extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use UsesModerationUuid;

    protected $fillable = [
        'blockable_type', 'blockable_id',
        'blocked_by_type', 'blocked_by_id',
        'reason', 'status', 'notes', 'expires_at',
        'lifted_at', 'lifted_by_type', 'lifted_by_id',
        'metadata',
    ];

    protected static string $ownerScopeConfigKey = 'moderation.features.owner';

    public function getTable(): string
    {
        return config('moderation.database.tables.blocks', 'moderation_blocks');
    }

    protected function casts(): array
    {
        return [
            'reason' => BlockReason::class,
            'status' => BlockStatus::class,
            'metadata' => 'array',
            'expires_at' => 'immutable_datetime',
            'lifted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function blockable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function blockedBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function liftedBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<Block>  $query
     * @return Builder<Block>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', BlockStatus::Active);
    }

    /**
     * @param  Builder<Block>  $query
     * @return Builder<Block>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', BlockStatus::Expired);
    }
}
