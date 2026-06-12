<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Database\Factories;

use AIArmada\Engagement\Models\EngagementCounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EngagementCounter>
 */
final class EngagementCounterFactory extends Factory
{
    protected $model = EngagementCounter::class;

    public function definition(): array
    {
        return [
            'counter_type' => 'view',
            'count_value' => 0,
        ];
    }
}
