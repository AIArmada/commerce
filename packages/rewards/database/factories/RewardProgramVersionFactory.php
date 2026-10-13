<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Models\RewardProgram;
use AIArmada\Rewards\Models\RewardProgramVersion;
use AIArmada\Rewards\Support\RewardMaterialTerms;
use AIArmada\Rewards\Support\RewardPresetBuilders;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RewardProgramVersion>
 */
class RewardProgramVersionFactory extends Factory
{
    protected $model = RewardProgramVersion::class;

    public function definition(): array
    {
        $preset = RewardPresetBuilders::for(RewardProgramMode::Points);
        $publishedAt = now()->subDay();

        return [
            'program_id' => RewardProgram::factory(),
            'version' => 1,
            'effective_from' => $publishedAt,
            'material_terms_hash' => RewardMaterialTerms::hashForVersion($preset['rules'], $preset['terms']),
            'rules' => $preset['rules'],
            'terms' => $preset['terms'],
            'compatible_with' => [],
            'published_by_type' => 'user',
            'published_by_id' => null,
            'published_at' => $publishedAt,
        ];
    }

    public function ofMode(RewardProgramMode $mode): static
    {
        return $this->state(function (array $attributes) use ($mode): array {
            $preset = RewardPresetBuilders::for($mode);

            return [
                'program_id' => RewardProgram::factory()->ofMode($mode),
                'material_terms_hash' => RewardMaterialTerms::hashForVersion($preset['rules'], $preset['terms']),
                'rules' => $preset['rules'],
                'terms' => $preset['terms'],
            ];
        });
    }
}
