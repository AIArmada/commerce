<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Models\RewardAwardEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardAwardEntry>
 */
class RewardAwardEntryFactory extends Factory
{
    protected $model = RewardAwardEntry::class;

    public function definition(): array
    {
        return [
            'award_id' => Str::uuid(),
            'seq' => 1,
            'from_status' => 'held',
            'to_status' => 'held',
            'actor_type' => 'system',
            'actor_id' => null,
            'actor_role' => 'system',
            'reason' => 'evaluation-recorded',
            'evidence' => null,
            'source_operation_id' => null,
            'decided_at' => now('UTC'),
        ];
    }
}
