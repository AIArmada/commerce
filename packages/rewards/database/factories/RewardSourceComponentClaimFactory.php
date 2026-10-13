<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Models\RewardSourceComponentClaim;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardSourceComponentClaim>
 */
class RewardSourceComponentClaimFactory extends Factory
{
    protected $model = RewardSourceComponentClaim::class;

    public function definition(): array
    {
        return [
            'source_business_key' => 'src-' . Str::lower(Str::random(8)),
            'component' => RewardComponent::Points,
            'beneficiary_role' => RewardBeneficiaryRole::Recipient,
            'family_key' => 'family-' . Str::lower(Str::random(6)),
            'program_id' => Str::uuid(),
            'award_id' => Str::uuid(),
            'compatible_snapshot' => [],
            'claimed_at' => now('UTC'),
        ];
    }
}
