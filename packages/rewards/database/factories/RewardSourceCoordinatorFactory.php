<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Models\RewardSourceCoordinator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardSourceCoordinator>
 */
class RewardSourceCoordinatorFactory extends Factory
{
    protected $model = RewardSourceCoordinator::class;

    public function definition(): array
    {
        return [
            'source_business_key' => 'src-' . Str::lower(Str::random(8)),
            'component' => RewardComponent::Points,
            'beneficiary_role' => RewardBeneficiaryRole::Recipient,
        ];
    }
}
