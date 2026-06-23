<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Enums\BundleInclusionMode;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Events\Models\EventTicketTypeProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventTicketTypeProduct>
 */
final class EventTicketTypeProductFactory extends Factory
{
    protected $model = EventTicketTypeProduct::class;

    public function definition(): array
    {
        return [
            'event_ticket_type_id' => EventTicketType::factory(),
            'quantity' => 1,
            'inclusion_mode' => BundleInclusionMode::Required,
            'sort_order' => 0,
        ];
    }

    public function required(): static
    {
        return $this->state(fn () => ['inclusion_mode' => BundleInclusionMode::Required]);
    }

    public function optional(): static
    {
        return $this->state(fn () => ['inclusion_mode' => BundleInclusionMode::Optional]);
    }
}
