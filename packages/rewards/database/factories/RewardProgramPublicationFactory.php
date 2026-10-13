<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Models\RewardProgram;
use AIArmada\Rewards\Models\RewardProgramPublication;
use AIArmada\Rewards\Models\RewardProgramVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RewardProgramPublication>
 */
class RewardProgramPublicationFactory extends Factory
{
    protected $model = RewardProgramPublication::class;

    public function definition(): array
    {
        return [
            'program_id' => RewardProgram::factory(),
            'version_id' => RewardProgramVersion::factory(),
            'previous_version_id' => null,
            'version' => 1,
            'effective_from' => now()->subDay(),
            'reason' => null,
            'published_by_type' => 'user',
            'published_by_id' => null,
            'published_at' => now()->subDay(),
        ];
    }
}
