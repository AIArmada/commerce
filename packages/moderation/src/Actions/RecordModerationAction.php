<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Actions;

use AIArmada\CommerceSupport\Contracts\OwnerScopeConfigurable;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Moderation\Contracts\RecordsModerationAction;
use AIArmada\Moderation\Enums\ModerationActionType;
use AIArmada\Moderation\Models\ModerationAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RecordModerationAction implements RecordsModerationAction
{
    public function execute(
        Model $actionable,
        ModerationActionType $type,
        string $reason,
        ?Model $actionedBy = null,
        ?array $metadata = null,
        ?string $notes = null,
    ): ModerationAction {
        $reason = mb_trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('A moderation reason is required.');
        }

        if (mb_strlen($reason) > 255) {
            throw new InvalidArgumentException('The moderation reason must not exceed 255 characters.');
        }

        if ($notes !== null) {
            $notes = mb_trim($notes);

            if (mb_strlen($notes) > 10000) {
                throw new InvalidArgumentException('Moderation notes must not exceed 10000 characters.');
            }
        }

        $this->assertMetadataWithinLimits($metadata);
        $this->validateOwnerScopedModel($actionable);

        if ($actionedBy !== null) {
            $this->validateOwnerScopedModel($actionedBy);
        }

        return DB::transaction(function () use ($actionable, $actionedBy, $type, $reason, $notes, $metadata): ModerationAction {
            return ModerationAction::create([
                'actionable_type' => $actionable->getMorphClass(),
                'actionable_id' => $actionable->getKey(),
                'actioned_by_type' => $actionedBy?->getMorphClass(),
                'actioned_by_id' => $actionedBy?->getKey(),
                'type' => $type,
                'reason' => $reason,
                'notes' => $notes,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function assertMetadataWithinLimits(?array $metadata): void
    {
        if ($metadata === null) {
            return;
        }

        if (count($metadata) > 50) {
            throw new InvalidArgumentException('Moderation metadata must not exceed 50 entries.');
        }

        $encoded = json_encode($metadata);

        if ($encoded === false || mb_strlen($encoded) > 65535) {
            throw new InvalidArgumentException('Moderation metadata must not exceed 65535 bytes.');
        }
    }

    private function validateOwnerScopedModel(Model $model): void
    {
        if (! config('moderation.owner.enabled', true)) {
            return;
        }

        if ($model instanceof OwnerScopeConfigurable && ! $model::ownerScopeConfig()->enabled) {
            return;
        }

        // Non-owner-scoped models are intentionally actionable from any owner
        // scope: a tenant moderates a shared identity without owning it.
        if (! $model instanceof OwnerScopeConfigurable && ! method_exists($model::class, 'ownerScopeConfig')) {
            return;
        }

        OwnerWriteGuard::findOrFailForOwner($model::class, $model->getKey());
    }
}
