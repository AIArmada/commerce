<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Traits;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Moderation\Actions\BlockEntityAction;
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
        return $this->blocks()->where('status', BlockStatus::Active);
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
            $q->where('status', BlockStatus::Active);
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

        /** @var BlockEntityAction $action */
        $action = app(BlockEntityAction::class);

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
