<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Moderation\Enums\BlockReason;
use AIArmada\Moderation\Enums\BlockStatus;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

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
    use HasUuids;

    protected $fillable = [
        'blockable_type', 'blockable_id',
        'blocked_by_type', 'blocked_by_id',
        'reason', 'status', 'notes', 'expires_at',
        'lifted_at', 'lifted_by_type', 'lifted_by_id',
        'metadata',
    ];

    protected static string $ownerScopeConfigKey = 'moderation.owner';

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
        return $query
            ->where('status', BlockStatus::Active)
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', CarbonImmutable::now());
            });
    }

    /**
     * @param  Builder<Block>  $query
     * @return Builder<Block>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', BlockStatus::Expired);
    }

    public function transitionTo(BlockStatus $status, ?CarbonImmutable $at = null): static
    {
        if ($this->exists && $this->status instanceof BlockStatus && $this->status === BlockStatus::Expired && $status !== BlockStatus::Expired) {
            throw new LogicException('An expired moderation block cannot transition to another status.');
        }

        $at ??= CarbonImmutable::now();

        $attributes = ['status' => $status];

        if ($status === BlockStatus::Lifted) {
            $attributes['lifted_at'] = $this->lifted_at ?? $at;
        }

        if ($status === BlockStatus::Active) {
            $attributes['lifted_at'] = null;
        }

        $this->fill($attributes);

        return $this;
    }

    public function expire(?CarbonImmutable $at = null): static
    {
        return $this->transitionTo(BlockStatus::Expired, $at);
    }
}
