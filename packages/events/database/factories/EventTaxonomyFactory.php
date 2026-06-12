<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventTaxonomy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventTaxonomy>
 */
final class EventTaxonomyFactory extends Factory
{
    protected $model = EventTaxonomy::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->lexify('taxonomy_????'),
            'name' => $this->faker->word() . ' Taxonomy',
        ];
    }
}
