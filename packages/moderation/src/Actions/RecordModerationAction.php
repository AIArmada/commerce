<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Actions;

use AIArmada\Moderation\Enums\ModerationActionType;
use AIArmada\Moderation\Models\ModerationAction;
use AIArmada\Moderation\Support\ModerationModelGuard;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

final readonly class RecordModerationAction
{
    public function __construct(private ModerationModelGuard $guard) {}

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
        }

        $this->guard->validatePayload($notes, $metadata);
        $this->guard->validateTarget($actionable);

        if ($actionedBy !== null) {
            $this->guard->validateActor($actionedBy);
        }

        $connection = (new ModerationAction)->getConnection();

        if ($actionable->getConnection() !== $connection) {
            throw new LogicException('Recording moderation requires a shared database connection with the target.');
        }

        return $connection->transaction(function () use ($actionable, $actionedBy, $type, $reason, $notes, $metadata): ModerationAction {
            $actionable->newQuery()->whereKey($actionable->getKey())->lockForUpdate()->firstOrFail();

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
}
