<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventAudience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAudience>
 */
final class EventAudienceFactory extends Factory
{
    protected $model = EventAudience::class;

    public function definition(): array
    {
        return [
            'audience_type' => 'demographic',
            'value' => $this->faker->word(),
        ];
    }
}
