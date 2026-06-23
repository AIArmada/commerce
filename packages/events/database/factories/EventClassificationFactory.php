<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventClassification;
use AIArmada\Events\Models\EventTaxonomy;
use AIArmada\Events\Models\EventTerm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventClassification>
 */
final class EventClassificationFactory extends Factory
{
    protected $model = EventClassification::class;

    public function definition(): array
    {
        $eventId = (string) Str::uuid();
        $taxonomyId = (string) Str::uuid();
        $termId = (string) Str::uuid();

        return [
            'event_id' => Event::factory()->state(['id' => $eventId]),
            'event_taxonomy_id' => EventTaxonomy::factory()->state(['id' => $taxonomyId]),
            'event_term_id' => EventTerm::factory()->state([
                'id' => $termId,
                'event_taxonomy_id' => $taxonomyId,
            ]),
            'is_primary' => false,
        ];
    }
}
