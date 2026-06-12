<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventEligibilityRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventEligibilityRule>
 */
final class EventEligibilityRuleFactory extends Factory
{
    protected $model = EventEligibilityRule::class;

    public function definition(): array
    {
        return [
            'rule_type' => 'age',
            'operator' => 'gte',
            'value' => '18',
            'effect' => 'allow',
        ];
    }
}
