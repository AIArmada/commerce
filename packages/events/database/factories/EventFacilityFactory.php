<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventFacility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventFacility>
 */
final class EventFacilityFactory extends Factory
{
    protected $model = EventFacility::class;

    public function definition(): array
    {
        return [
            'availability' => 'available',
            'visibility' => 'public',
        ];
    }
}
