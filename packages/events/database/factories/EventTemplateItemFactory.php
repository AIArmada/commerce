<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventTemplateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventTemplateItem>
 */
final class EventTemplateItemFactory extends Factory
{
    protected $model = EventTemplateItem::class;

    public function definition(): array
    {
        return [
            'event_template_id' => EventTemplateFactory::new(),
            'item_type' => 'session',
            'payload' => [],
            'status' => 'active',
        ];
    }
}
