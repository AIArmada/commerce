<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Enums\RewardRecoveryKind;
use AIArmada\Rewards\Enums\RewardRecoveryStatus;
use AIArmada\Rewards\Models\RewardRecovery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardRecovery>
 */
class RewardRecoveryFactory extends Factory
{
    protected $model = RewardRecovery::class;

    public function definition(): array
    {
        return [
            'recovery_key' => 'recovery-' . Str::lower(Str::random(12)),
            'award_id' => Str::uuid(),
            'payout_request_id' => null,
            'grant_id' => null,
            'kind' => RewardRecoveryKind::Receivable,
            'status' => RewardRecoveryStatus::Open,
            'amount_minor' => null,
            'currency' => null,
            'points' => null,
            'unit' => null,
            'evidence' => null,
            'actor_type' => 'user',
            'actor_id' => Str::uuid(),
            'actor_role' => 'staff',
            'opened_at' => now('UTC'),
            'settled_at' => null,
            'written_off_at' => null,
        ];
    }
}
