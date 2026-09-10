<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Traits;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Moderation\Contracts\BlocksEntity;
use AIArmada\Moderation\Enums\BlockReason;
use AIArmada\Moderation\Enums\BlockStatus;
use AIArmada\Moderation\Models\Block;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait HasBlocks
{
    public static function bootHasBlocks(): void
    {
        static::deleting(function (Model $model): void {
            $model->blocks()
                ->where('status', BlockStatus::Active)
                ->get()
                ->each(function (Block $block): void {
                    $block->expire()->save();
                });
        });
    }

    /**
     * @return MorphMany<Block, $this>
     */
    public function blocks(): MorphMany
    {
        return $this->morphMany(Block::class, 'blockable');
    }

    /**
     * @return MorphMany<Block, $this>
     */
    public function activeBlocks(): MorphMany
    {
        return $this->blocks()->active();
    }

    public function isBlocked(): bool
    {
        return $this->activeBlocks()->exists();
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeWhereNotBlocked(Builder $query): Builder
    {
        return $query->whereDoesntHave('blocks', function (Builder $q): void {
            $q->active();
        });
    }

    public function block(
        ?string $reason = null,
        ?string $notes = null,
        ?CarbonInterface $expiresAt = null,
        ?string $blockedById = null,
        ?string $blockedByType = null,
    ): Block {
        $blockedBy = $this->resolveBlockedBy($blockedById, $blockedByType);

        /** @var BlocksEntity $action */
        $action = app(BlocksEntity::class);

        return $action->execute(
            blockable: $this,
            blockedBy: $blockedBy,
            reason: $reason !== null ? BlockReason::tryFrom($reason) : null,
            notes: $notes,
            expiresAt: $expiresAt,
        );
    }

    private function resolveBlockedBy(?string $id, ?string $type): ?Model
    {
        if ($id === null || $type === null || ! is_a($type, Model::class, true)) {
            return null;
        }

        if (method_exists($type, 'ownerScopeConfig') && $type::ownerScopeConfig()->enabled) {
            return OwnerWriteGuard::findOrFailForOwner($type, $id);
        }

        /** @var Model|null $model */
        $model = (new $type)->newQuery()->find($id);

        return $model;
    }
}
