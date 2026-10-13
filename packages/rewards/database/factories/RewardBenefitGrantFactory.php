<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Enums\RewardBenefitGrantStatus;
use AIArmada\Rewards\Models\RewardBenefitGrant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardBenefitGrant>
 */
class RewardBenefitGrantFactory extends Factory
{
    protected $model = RewardBenefitGrant::class;

    public function definition(): array
    {
        return [
            'grant_key' => 'grant-' . Str::lower(Str::random(12)),
            'item_id' => Str::uuid(),
            'recipient_type' => 'user',
            'recipient_id' => Str::uuid(),
            'award_id' => null,
            'reservation_key' => null,
            'quantity' => 1,
            'status' => RewardBenefitGrantStatus::Reserved,
            'fulfillment_adapter' => 'manual',
            'external_ref' => null,
            'evidence' => null,
            'actor_type' => 'user',
            'actor_id' => Str::uuid(),
            'actor_role' => 'member',
            'reserved_at' => now('UTC'),
            'issued_at' => null,
            'consumed_at' => null,
            'revoked_at' => null,
            'expired_at' => null,
        ];
    }
}
