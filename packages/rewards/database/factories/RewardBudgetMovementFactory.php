<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Enums\RewardBudgetMovementKind;
use AIArmada\Rewards\Models\RewardBudgetMovement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardBudgetMovement>
 */
class RewardBudgetMovementFactory extends Factory
{
    protected $model = RewardBudgetMovement::class;

    public function definition(): array
    {
        return [
            'movement_key' => 'budget-' . Str::lower(Str::random(12)),
            'program_id' => Str::uuid(),
            'currency' => 'MYR',
            'kind' => RewardBudgetMovementKind::Reserve,
            'award_id' => null,
            'amount_minor' => 1000,
            'actor_type' => 'system',
            'actor_id' => null,
            'actor_role' => 'system',
            'decided_at' => now('UTC'),
        ];
    }
}
