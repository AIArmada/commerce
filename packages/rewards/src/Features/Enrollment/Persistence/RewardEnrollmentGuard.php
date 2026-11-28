<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Enrollment\Persistence;

use AIArmada\Rewards\Features\Enrollment\Models\RewardEnrollment;
use AIArmada\Rewards\Support\RewardProgramGuard;
use InvalidArgumentException;

final class RewardEnrollmentGuard
{
    public static function findOrFail(string $id): RewardEnrollment
    {
        $enrollment = RewardEnrollment::query()->whereKey($id)->first();

        if (! $enrollment instanceof RewardEnrollment) {
            throw new InvalidArgumentException('Unknown reward enrollment.');
        }

        RewardProgramGuard::findProgramOrFail($enrollment->program_id);

        return $enrollment;
    }
}
