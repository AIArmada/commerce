<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Models\RewardFundingEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardFundingEntry>
 */
class RewardFundingEntryFactory extends Factory
{
    protected $model = RewardFundingEntry::class;

    public function definition(): array
    {
        return [
            'program_id' => Str::uuid(),
            'currency' => 'MYR',
            'amount_minor' => 100000,
            'source_ref' => 'src-' . Str::lower(Str::random(8)),
            'source_id' => Str::uuid(),
            'evidence' => null,
            'funded_by_type' => 'user',
            'funded_by_id' => Str::uuid(),
            'funded_by_role' => 'staff',
            'funded_at' => now('UTC'),
        ];
    }
}
