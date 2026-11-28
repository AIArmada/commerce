<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Programs\Queries;

use AIArmada\Rewards\Features\Programs\Api\RewardProgramLockApi;
use AIArmada\Rewards\Features\Programs\Models\RewardProgram;
use AIArmada\Rewards\Support\RewardProgramGuard;
use InvalidArgumentException;

final readonly class RewardProgramLockQuery implements RewardProgramLockApi
{
    public function lockScopedProgram(string $programId): void
    {
        RewardProgramGuard::findProgramOrFail($programId);

        if (! RewardProgram::query()->whereKey($programId)->lockForUpdate()->first(['id']) instanceof RewardProgram) {
            throw new InvalidArgumentException('Unknown reward program.');
        }
    }
}
