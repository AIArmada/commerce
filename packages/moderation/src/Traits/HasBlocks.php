<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Traits;

use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Moderation\Contracts\BlocksEntity;
use AIArmada\Moderation\Enums\BlockReason;
use AIArmada\Moderation\Enums\BlockStatus;
use AIArmada\Moderation\Models\Block;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** @mixin Model */
trait HasBlocks
{
    public static function bootHasBlocks(): void
    {
        static::deleting(function (Model $model): void {
            DB::transaction(function () use ($model): void {
                $model->blocks()
                    ->withoutGlobalScope(OwnerScope::class)
                    ->where('status', BlockStatus::Active)
                    ->chunkById(500, function (Collection $blocks): void {
                        Block::withoutOwnerScope()
                            ->whereKey($blocks->modelKeys())
                            ->update([
                                'status' => BlockStatus::Expired->value,
                                'updated_at' => CarbonImmutable::now(),
                            ]);
                    });
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

    private function resolveBlockedBy(?string $id, ?string $type): ?Model
    {
        if ($id === null || $type === null) {
            return null;
        }

        $class = (string) (Relation::morphMap()[$type] ?? $type);

        if (! is_a($class, Model::class, true)) {
            return null;
        }

        $this->assertResolvableBlockedByActor($class, $type);

        if (method_exists($class, 'ownerScopeConfig') && $class::ownerScopeConfig()->enabled) {
            return OwnerWriteGuard::findOrFailForOwner($class, $id);
        }

        /** @var Model|null $model */
        $model = (new $class)->newQuery()->find($id);

        return $model;
    }

    private function assertResolvableBlockedByActor(string $class, string $type): void
    {
        /** @var list<string> $allowed */
        $allowed = config('moderation.actors.allowed_types', []);

        if ($allowed === []) {
            return;
        }

        if (! in_array($class, $allowed, true) && ! in_array($type, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('The actor type [%s] is not allowed to moderate.', $type));
        }
    }
}
