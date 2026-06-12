<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventRegistrationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRegistrationItem>
 */
final class EventRegistrationItemFactory extends Factory
{
    protected $model = EventRegistrationItem::class;

    public function definition(): array
    {
        return [
            'quantity' => 1,
            'status' => 'pending',
        ];
    }
}
