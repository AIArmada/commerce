<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Enums\RewardEnrollmentStatus;
use AIArmada\Rewards\Models\RewardEnrollment;
use AIArmada\Rewards\Models\RewardProgram;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardEnrollment>
 */
class RewardEnrollmentFactory extends Factory
{
    protected $model = RewardEnrollment::class;

    public function definition(): array
    {
        return [
            'program_id' => RewardProgram::factory(),
            'member_type' => 'user',
            'member_id' => (string) Str::uuid(),
            'status' => RewardEnrollmentStatus::Active,
            'current_acceptance_id' => null,
            'enrolled_at' => now(),
            'left_at' => null,
        ];
    }

    public function left(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RewardEnrollmentStatus::Left,
            'left_at' => now(),
        ]);
    }
}
