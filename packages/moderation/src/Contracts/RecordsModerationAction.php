<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Contracts;

use AIArmada\Moderation\Enums\ModerationActionType;
use AIArmada\Moderation\Models\ModerationAction;
use Illuminate\Database\Eloquent\Model;

interface RecordsModerationAction
{
    public function execute(
        Model $actionable,
        ModerationActionType $type,
        string $reason,
        ?Model $actionedBy = null,
        ?array $metadata = null,
    ): ModerationAction;
}
