<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventSeriesRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSeriesRule>
 */
final class EventSeriesRuleFactory extends Factory
{
    protected $model = EventSeriesRule::class;

    public function definition(): array
    {
        return [
            'rule_type' => 'recurrence',
            'operator' => 'weekly',
        ];
    }
}
