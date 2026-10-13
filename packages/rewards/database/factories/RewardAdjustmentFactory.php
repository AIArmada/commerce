<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Enums\RewardAdjustmentKind;
use AIArmada\Rewards\Enums\RewardAdjustmentStatus;
use AIArmada\Rewards\Models\RewardAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardAdjustment>
 */
class RewardAdjustmentFactory extends Factory
{
    protected $model = RewardAdjustment::class;

    public function definition(): array
    {
        return [
            'award_id' => null,
            'appeal_id' => null,
            'linked_adjustment_id' => null,
            'kind' => RewardAdjustmentKind::Adjustment,
            'status' => RewardAdjustmentStatus::Open,
            'operation_key' => 'adjustment:test:' . Str::lower(Str::random(8)),
            'amount_snapshot' => null,
            'actor_type' => 'system',
            'actor_id' => null,
            'actor_role' => 'system',
            'reason' => 'test-adjustment',
            'evidence' => null,
            'resolved_by_type' => null,
            'resolved_by_id' => null,
            'resolve_reason' => null,
            'opened_at' => now('UTC'),
            'resolved_at' => null,
        ];
    }
}
