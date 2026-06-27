<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Moderation\Contracts\BlocksEntity;
use AIArmada\Moderation\Enums\BlockReason;
use AIArmada\Moderation\Enums\BlockStatus;
use AIArmada\Moderation\Models\Block;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
            return Block::create([
                'blockable_type' => $blockable->getMorphClass(),
                'blockable_id' => $blockable->getKey(),
                'blocked_by_type' => $blockedBy?->getMorphClass(),
                'blocked_by_id' => $blockedBy?->getKey(),
                'reason' => $reason,
                'status' => BlockStatus::Active,
                'notes' => $notes,
                'expires_at' => $expiresAt,
                'metadata' => $metadata,
            ]);
        });
    }

    private function validateOwnerScopedModel(Model $model): void
    {
        if (! config('moderation.features.owner.enabled', true)) {
            return;
        }

        try {
            OwnerWriteGuard::findOrFailForOwner($model::class, $model->getKey());
        } catch (InvalidArgumentException) {
            return;
        }
    }
}
