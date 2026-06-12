<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventClassification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventClassification>
 */
final class EventClassificationFactory extends Factory
{
    protected $model = EventClassification::class;

    public function definition(): array
    {
        return [
            'is_primary' => false,
        ];
    }
}
