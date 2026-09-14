<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Actions;

use AIArmada\CommerceSupport\Contracts\OwnerScopeConfigurable;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Moderation\Contracts\BlocksEntity;
use AIArmada\Moderation\Enums\BlockReason;
use AIArmada\Moderation\Enums\BlockStatus;
use AIArmada\Moderation\Models\Block;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class BlockEntityAction implements BlocksEntity
{
    public function execute(
        Model $blockable,
        ?Model $blockedBy = null,
        ?BlockReason $reason = null,
        ?string $notes = null,
        ?CarbonImmutable $expiresAt = null,
        ?array $metadata = null,
    ): Block {
        $reason ??= BlockReason::Other;
        $expiresAt ??= CarbonImmutable::now()->addDays(
            (int) config('moderation.defaults.block_duration_days', 30),
        );

        $this->validateOwnerScopedModel($blockable);

        if ($blockedBy !== null) {
            $this->validateOwnerScopedModel($blockedBy);
        }

        return DB::transaction(function () use ($blockable, $blockedBy, $reason, $notes, $expiresAt, $metadata): Block {
            $blockable->newQuery()->whereKey($blockable->getKey())->lockForUpdate()->first();

            $existing = Block::query()
                ->where('blockable_type', $blockable->getMorphClass())
                ->where('blockable_id', $blockable->getKey())
                ->where('status', BlockStatus::Active)
                ->where(function (Builder $query): void {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', CarbonImmutable::now());
                })
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Block) {
                if ($existing->expires_at !== null && $expiresAt->greaterThan($existing->expires_at)) {
                    $existing->forceFill(['expires_at' => $expiresAt])->save();
                }

                return $existing;
            }

            $block = new Block([
                'blockable_type' => $blockable->getMorphClass(),
                'blockable_id' => $blockable->getKey(),
                'blocked_by_type' => $blockedBy?->getMorphClass(),
                'blocked_by_id' => $blockedBy?->getKey(),
                'reason' => $reason,
                'notes' => $notes,
                'metadata' => $metadata,
            ]);
            $block->forceFill(['expires_at' => $expiresAt]);

            $block->transitionTo(BlockStatus::Active);
            $block->save();

            return $block;
        });
    }

    private function validateOwnerScopedModel(Model $model): void
    {
        if (! config('moderation.owner.enabled', true)) {
            return;
        }

        if ($model instanceof OwnerScopeConfigurable && ! $model::ownerScopeConfig()->enabled) {
            return;
        }

        // Non-owner-scoped models are intentionally blockable from any owner
        // scope: a tenant blocks a shared identity without owning it.
        if (! $model instanceof OwnerScopeConfigurable && ! method_exists($model::class, 'ownerScopeConfig')) {
            return;
        }

        OwnerWriteGuard::findOrFailForOwner($model::class, $model->getKey());
    }
}
