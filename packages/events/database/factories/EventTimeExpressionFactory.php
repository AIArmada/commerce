<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventTimeExpression;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventTimeExpression>
 */
final class EventTimeExpressionFactory extends Factory
{
    protected $model = EventTimeExpression::class;

    public function definition(): array
    {
        return [
            'time_mode' => 'fixed',
        ];
    }
}
