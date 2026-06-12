<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventLanguage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventLanguage>
 */
final class EventLanguageFactory extends Factory
{
    protected $model = EventLanguage::class;

    public function definition(): array
    {
        return [
            'language_code' => $this->faker->languageCode(),
            'usage_type' => 'presentation',
        ];
    }
}
