<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Enums\EventRegistrationQuestionStatus;
use AIArmada\Events\Enums\EventRegistrationQuestionType;
use AIArmada\Events\Models\EventRegistrationQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRegistrationQuestion>
 */
final class EventRegistrationQuestionFactory extends Factory
{
    protected $model = EventRegistrationQuestion::class;

    public function definition(): array
    {
        return [
            'field_key' => $this->faker->unique()->lexify('question_????'),
            'question' => $this->faker->sentence(),
            'type' => EventRegistrationQuestionType::Text->value,
            'is_required' => false,
            'status' => EventRegistrationQuestionStatus::Active->value,
        ];
    }
}
