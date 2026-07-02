<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Database\Factories;

use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Models\TicketTypeComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketTypeComponent>
 */
final class TicketTypeComponentFactory extends Factory
{
    protected $model = TicketTypeComponent::class;

    public function definition(): array
    {
        return [
            'parent_ticket_type_id' => TicketType::factory(),
            'component_ticket_type_id' => TicketType::factory(),
            'quantity' => 1,
        ];
    }
}
