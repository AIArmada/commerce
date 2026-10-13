<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Enums\RewardAppealStatus;
use AIArmada\Rewards\Models\RewardAppeal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardAppeal>
 */
class RewardAppealFactory extends Factory
{
    protected $model = RewardAppeal::class;

    public function definition(): array
    {
        return [
            'award_id' => Str::uuid(),
            'seq' => 1,
            'status' => RewardAppealStatus::Open,
            'opened_by_type' => 'user',
            'opened_by_id' => (string) Str::uuid(),
            'opened_by_role' => 'member',
            'reason' => 'test-appeal',
            'evidence' => null,
            'window_ends_at' => now('UTC')->addDays(30),
            'opened_at' => now('UTC'),
            'in_review_at' => null,
            'upheld_at' => null,
            'overturned_at' => null,
            'closed_at' => null,
        ];
    }
}
