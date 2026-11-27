<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Traits;

use AIArmada\Moderation\Actions\BlockEntityAction;
use AIArmada\Moderation\Actions\ExpireDeletedEntityBlocksAction;
use AIArmada\Moderation\Enums\BlockReason;
use AIArmada\Moderation\Models\Block;
use AIArmada\Moderation\Support\ModerationModelGuard;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;

/** @mixin Model */
trait HasBlocks
{
    public static function bootHasBlocks(): void
    {
        static::deleting(function (Model $model): void {
            app(ExpireDeletedEntityBlocksAction::class)->execute($model);
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
        $blockedBy = app(ModerationModelGuard::class)->resolveActor($blockedById, $blockedByType);

        $action = app(BlockEntityAction::class);

        return $action->execute(
            blockable: $this,
            blockedBy: $blockedBy,
            reason: $reason !== null ? $this->resolveReason($reason) : null,
            notes: $notes,
            expiresAt: $expiresAt === null ? null : CarbonImmutable::createFromInterface($expiresAt),
        );
    }

    private function resolveReason(string $reason): BlockReason
    {
        return BlockReason::tryFrom($reason)
            ?? throw new InvalidArgumentException(sprintf('Unknown block reason [%s].', $reason));
    }
}
