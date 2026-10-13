<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Enums\RewardBenefitStockKind;
use AIArmada\Rewards\Models\RewardBenefitStockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardBenefitStockMovement>
 */
class RewardBenefitStockMovementFactory extends Factory
{
    protected $model = RewardBenefitStockMovement::class;

    public function definition(): array
    {
        return [
            'movement_key' => 'stock-' . Str::lower(Str::random(12)),
            'item_id' => Str::uuid(),
            'kind' => RewardBenefitStockKind::Reserve,
            'grant_id' => null,
            'quantity' => 1,
            'actor_type' => 'user',
            'actor_id' => Str::uuid(),
            'actor_role' => 'member',
            'decided_at' => now('UTC'),
        ];
    }
}
