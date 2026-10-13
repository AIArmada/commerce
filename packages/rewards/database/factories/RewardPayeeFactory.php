<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Enums\RewardPayeeStatus;
use AIArmada\Rewards\Models\RewardPayee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardPayee>
 */
class RewardPayeeFactory extends Factory
{
    protected $model = RewardPayee::class;

    public function definition(): array
    {
        return [
            'recipient_type' => 'user',
            'recipient_id' => Str::uuid(),
            'method' => 'manual',
            'account_ref' => 'acct-' . Str::lower(Str::random(8)),
            'account_display' => 'Manual handoff account',
            'status' => RewardPayeeStatus::Pending,
            'evidence' => null,
            'verified_by' => null,
            'verified_at' => null,
            'rejected_at' => null,
            'superseded_at' => null,
        ];
    }
}
