<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventTerm>
 */
final class EventTermFactory extends Factory
{
    protected $model = EventTerm::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->lexify('term_????'),
            'name' => $this->faker->word(),
        ];
    }
}
