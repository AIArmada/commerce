<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Traits;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Moderation\Actions\RecordModerationAction;
use AIArmada\Moderation\Enums\ModerationActionType;
use AIArmada\Moderation\Models\ModerationAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait HasModerationActions
{
    /**
     * @return MorphMany<ModerationAction, $this>
     */
    public function moderationActions(): MorphMany
    {
        return $this->morphMany(ModerationAction::class, 'actionable');
    }

    public function recordModerationAction(
        ModerationActionType $type,
        string $reason,
        ?array $metadata = null,
        ?string $actionedById = null,
        ?string $actionedByType = null,
    ): ModerationAction {
        $actionedBy = $this->resolveActionedBy($actionedById, $actionedByType);

        /** @var RecordModerationAction $action */
        $action = app(RecordModerationAction::class);

        return $action->execute(
            actionable: $this,
            type: $type,
            reason: $reason,
            actionedBy: $actionedBy,
            metadata: $metadata,
        );
    }

    private function resolveActionedBy(?string $id, ?string $type): ?Model
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
