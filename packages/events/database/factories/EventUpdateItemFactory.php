<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventUpdateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventUpdateItem>
 */
final class EventUpdateItemFactory extends Factory
{
    protected $model = EventUpdateItem::class;

    public function definition(): array
    {
        return [
            'field_key' => $this->faker->lexify('field_????'),
        ];
    }
}
