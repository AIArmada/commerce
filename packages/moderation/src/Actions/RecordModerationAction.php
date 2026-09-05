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

final class RecordModerationAction implements RecordsModerationAction
{
    public function execute(
        Model $actionable,
        ModerationActionType $type,
        string $reason,
        ?Model $actionedBy = null,
        ?array $metadata = null,
    ): ModerationAction {
        $this->validateOwnerScopedModel($actionable);

        if ($actionedBy !== null) {
            $this->validateOwnerScopedModel($actionedBy);
        }

        return DB::transaction(function () use ($actionable, $actionedBy, $type, $reason, $metadata): ModerationAction {
            return ModerationAction::create([
                'actionable_type' => $actionable->getMorphClass(),
                'actionable_id' => $actionable->getKey(),
                'actioned_by_type' => $actionedBy?->getMorphClass(),
                'actioned_by_id' => $actionedBy?->getKey(),
                'type' => $type,
                'reason' => $reason,
                'metadata' => $metadata,
            ]);
        });
    }

    private function validateOwnerScopedModel(Model $model): void
    {
        if (! config('moderation.features.owner.enabled', true)) {
            return;
        }

        if (! $model instanceof OwnerScopeConfigurable && ! method_exists($model::class, 'scopeForOwner')) {
            return;
        }

        if ($model instanceof OwnerScopeConfigurable && ! $model::ownerScopeConfig()->enabled) {
            return;
        }

        OwnerWriteGuard::findOrFailForOwner($model::class, $model->getKey());
    }
}
