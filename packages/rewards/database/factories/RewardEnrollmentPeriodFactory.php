<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Models\RewardEnrollment;
use AIArmada\Rewards\Models\RewardEnrollmentAcceptance;
use AIArmada\Rewards\Models\RewardEnrollmentPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RewardEnrollmentPeriod>
 */
class RewardEnrollmentPeriodFactory extends Factory
{
    protected $model = RewardEnrollmentPeriod::class;

    public function definition(): array
    {
        return [
            'enrollment_id' => RewardEnrollment::factory(),
            'seq' => 1,
            'joined_at' => now(),
            'left_at' => null,
            'join_acceptance_id' => RewardEnrollmentAcceptance::factory(),
            'leave_reason' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'left_at' => now(),
            'leave_reason' => 'member-left',
        ]);
    }
}
