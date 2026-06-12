<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventAudienceProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAudienceProfile>
 */
final class EventAudienceProfileFactory extends Factory
{
    protected $model = EventAudienceProfile::class;

    public function definition(): array
    {
        return [
            'is_child_friendly' => $this->faker->boolean(),
            'min_age' => $this->faker->numberBetween(0, 18),
            'max_age' => $this->faker->numberBetween(18, 100),
        ];
    }
}
