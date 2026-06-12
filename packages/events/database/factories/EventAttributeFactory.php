<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAttribute>
 */
final class EventAttributeFactory extends Factory
{
    protected $model = EventAttribute::class;

    public function definition(): array
    {
        return [
            'attribute_key' => $this->faker->lexify('attr_????'),
            'attribute_value' => $this->faker->word(),
        ];
    }
}
