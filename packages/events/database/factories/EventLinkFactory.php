<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventLink>
 */
final class EventLinkFactory extends Factory
{
    protected $model = EventLink::class;

    public function definition(): array
    {
        return [
            'link_type' => 'website',
            'label' => $this->faker->word(),
            'url' => $this->faker->url(),
            'visibility' => 'public',
        ];
    }
}
