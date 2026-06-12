<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRevision>
 */
final class EventRevisionFactory extends Factory
{
    protected $model = EventRevision::class;

    public function definition(): array
    {
        return [
            'version_no' => 1,
            'revision_type' => 'auto',
            'status' => 'draft',
        ];
    }
}
