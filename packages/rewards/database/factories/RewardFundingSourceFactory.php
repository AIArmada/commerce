<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Models\RewardFundingSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardFundingSource>
 */
class RewardFundingSourceFactory extends Factory
{
    protected $model = RewardFundingSource::class;

    public function definition(): array
    {
        return [
            'source_ref' => 'src-' . Str::lower(Str::random(8)),
            'currency' => 'MYR',
            'amount_minor' => 100000,
            'evidence' => null,
            'funded_by_type' => 'user',
            'funded_by_id' => Str::uuid(),
            'funded_by_role' => 'staff',
            'funded_at' => now('UTC'),
        ];
    }
}
