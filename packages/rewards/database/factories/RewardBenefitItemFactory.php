<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Enums\RewardBenefitItemStatus;
use AIArmada\Rewards\Models\RewardBenefitItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardBenefitItem>
 */
class RewardBenefitItemFactory extends Factory
{
    protected $model = RewardBenefitItem::class;

    public function definition(): array
    {
        return [
            'program_id' => Str::uuid(),
            'benefit_key' => 'benefit-' . Str::lower(Str::random(8)),
            'title' => 'Test benefit item',
            'conditions' => ['Member account in good standing'],
            'cost_points' => 100,
            'cost_unit' => 'pts',
            'direct_entitlement' => false,
            'capacity' => 100,
            'fulfillment_config' => null,
            'status' => RewardBenefitItemStatus::Active,
            'expires_at' => null,
            'retired_at' => null,
        ];
    }
}
