<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventRegistrationAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRegistrationAnswer>
 */
final class EventRegistrationAnswerFactory extends Factory
{
    protected $model = EventRegistrationAnswer::class;

    public function definition(): array
    {
        return [
            'field_key' => $this->faker->lexify('field_????'),
            'question' => $this->faker->sentence(),
            'answer' => $this->faker->sentence(),
        ];
    }
}
