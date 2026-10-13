<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Models\RewardAppealDecision;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardAppealDecision>
 */
class RewardAppealDecisionFactory extends Factory
{
    protected $model = RewardAppealDecision::class;

    public function definition(): array
    {
        return [
            'appeal_id' => Str::uuid(),
            'seq' => 1,
            'from_status' => 'open',
            'to_status' => 'open',
            'actor_type' => 'user',
            'actor_id' => (string) Str::uuid(),
            'actor_role' => 'member',
            'reason' => 'test-decision',
            'evidence' => null,
            'decided_at' => now('UTC'),
        ];
    }
}
