<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Enums\RewardPointLotStatus;
use AIArmada\Rewards\Models\RewardPointLot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardPointLot>
 */
class RewardPointLotFactory extends Factory
{
    protected $model = RewardPointLot::class;

    public function definition(): array
    {
        return [
            'recipient_type' => 'user',
            'recipient_id' => Str::uuid(),
            'family_key' => 'family-' . Str::lower(Str::random(6)),
            'program_id' => null,
            'award_id' => null,
            'seq' => 1,
            'unit' => 'pts',
            'credited_points' => 10,
            'source_operation_key' => 'credit:test:' . Str::lower(Str::random(8)),
            'available_at' => now('UTC'),
            'expires_at' => null,
            'status' => RewardPointLotStatus::Open,
            'credited_at' => now('UTC'),
            'depleted_at' => null,
            'expired_at' => null,
        ];
    }
}
