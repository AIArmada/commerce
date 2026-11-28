<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Programs\Queries;

use AIArmada\Rewards\Data\RewardVersionSnapshot;
use AIArmada\Rewards\Features\Programs\Models\RewardProgramVersion;

final readonly class RewardVersionReadinessMapper
{
    public static function version(?RewardProgramVersion $version): ?RewardVersionSnapshot
    {
        return $version === null ? null : new RewardVersionSnapshot(
            id: (string) $version->getKey(),
            componentTypes: $version->componentTypes(),
            rules: $version->rules,
        );
    }
}
