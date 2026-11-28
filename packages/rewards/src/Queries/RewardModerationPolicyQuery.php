<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Queries;

final readonly class RewardModerationPolicyQuery
{
    /** @return list<string> */
    public static function outcomes(): array
    {
        $configured = config('rewards.moderated_outcomes', []);

        return is_array($configured) ? array_values(array_filter($configured, is_string(...))) : [];
    }
}
