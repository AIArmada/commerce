<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventSeatSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSeatSection>
 */
final class EventSeatSectionFactory extends Factory
{
    protected $model = EventSeatSection::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word() . ' Section',
            'section_type' => 'general',
        ];
    }
}
