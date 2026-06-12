<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventLocation>
 */
final class EventLocationFactory extends Factory
{
    protected $model = EventLocation::class;

    public function definition(): array
    {
        return [
            'location_role' => 'primary',
            'visibility' => 'public',
            'status' => 'active',
        ];
    }
}
