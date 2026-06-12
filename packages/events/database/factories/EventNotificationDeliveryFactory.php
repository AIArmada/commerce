<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventNotificationDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventNotificationDelivery>
 */
final class EventNotificationDeliveryFactory extends Factory
{
    protected $model = EventNotificationDelivery::class;

    public function definition(): array
    {
        return [
            'channel' => 'email',
            'status' => 'pending',
        ];
    }
}
