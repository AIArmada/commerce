<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Traits;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Moderation\Contracts\RecordsModerationAction;
use AIArmada\Moderation\Enums\ModerationActionType;
use AIArmada\Moderation\Models\ModerationAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

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
        ?string $notes = null,
    ): ModerationAction {
        $actionedBy = $this->resolveActionedBy($actionedById, $actionedByType);

        /** @var RecordsModerationAction $action */
        $action = app(RecordsModerationAction::class);

        return $action->execute(
            actionable: $this,
            type: $type,
            reason: $reason,
            actionedBy: $actionedBy,
            metadata: $metadata,
            notes: $notes,
        );
    }

    private function resolveActionedBy(?string $id, ?string $type): ?Model
    {
        if ($id === null || $type === null) {
            return null;
        }

        $class = (string) (Relation::morphMap()[$type] ?? $type);

        if (! is_a($class, Model::class, true)) {
            return null;
        }

        $this->assertResolvableActionedByActor($class, $type);

        if (method_exists($class, 'ownerScopeConfig') && $class::ownerScopeConfig()->enabled) {
            return OwnerWriteGuard::findOrFailForOwner($class, $id);
        }

        /** @var Model|null $model */
        $model = (new $class)->newQuery()->find($id);

        return $model;
    }

    private function assertResolvableActionedByActor(string $class, string $type): void
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
