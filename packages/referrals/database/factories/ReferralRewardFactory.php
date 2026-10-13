<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Database\Factories;

use AIArmada\Referrals\Enums\ReferralRewardSide;
use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Models\ReferralReward;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ReferralReward> */
final class ReferralRewardFactory extends Factory
{
    protected $model = ReferralReward::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $invite = ReferralInvite::factory()->create();
        $side = ReferralRewardSide::Referrer;

        return [
            'invite_id' => $invite->getKey(),
            'program_id' => $invite->program_id,
            'side' => $side,
            'beneficiary_type' => 'user',
            'beneficiary_id' => (string) Str::uuid(),
            'driver' => 'manual',
            'spec' => ['driver' => 'manual'],
            'idempotency_key' => hash('sha256', 'referrals:reward:' . $invite->getKey() . ':' . $side->value),
        ];
    }
}
