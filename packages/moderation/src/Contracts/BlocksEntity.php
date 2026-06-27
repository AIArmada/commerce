<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Contracts;

use AIArmada\Moderation\Enums\BlockReason;
use AIArmada\Moderation\Models\Block;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

interface BlocksEntity
{
    public function execute(
        Model $blockable,
        ?Model $blockedBy = null,
        ?BlockReason $reason = null,
        ?string $notes = null,
        ?CarbonImmutable $expiresAt = null,
        ?array $metadata = null,
    ): Block;
}
