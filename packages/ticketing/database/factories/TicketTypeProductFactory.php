<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Database\Factories;

use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Models\TicketTypeProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketTypeProduct>
 */
final class TicketTypeProductFactory extends Factory
{
    protected $model = TicketTypeProduct::class;

    public function definition(): array
    {
        return [
            'ticket_type_id' => TicketType::factory(),
            'product_type' => null,
            'product_id' => null,
            'quantity' => 1,
            'inclusion_mode' => 'required',
            'sort_order' => 0,
        ];
    }
}
