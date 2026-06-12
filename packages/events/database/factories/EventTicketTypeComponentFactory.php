<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventTicketTypeComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventTicketTypeComponent>
 */
final class EventTicketTypeComponentFactory extends Factory
{
    protected $model = EventTicketTypeComponent::class;

    public function definition(): array
    {
        return [
            'quantity' => 1,
        ];
    }
}
