<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Actions;

use AIArmada\Moderation\Enums\BlockReason;
use AIArmada\Moderation\Enums\BlockStatus;
use AIArmada\Moderation\Models\Block;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use AIArmada\Moderation\Support\ModerationModelGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class BlockEntityAction
{
    public function __construct(private ModerationModelGuard $guard) {}

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

        $this->guard->validateTarget($blockable);

        if ($blockedBy !== null) {
            $this->guard->validateActor($blockedBy);
        }

        return DB::transaction(function () use ($blockable, $blockedBy, $reason, $notes, $expiresAt, $metadata): Block {
            $blockable->newQuery()->whereKey($blockable->getKey())->lockForUpdate()->firstOrFail();

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

}
