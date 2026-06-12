<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventSubmissionLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSubmissionLog>
 */
final class EventSubmissionLogFactory extends Factory
{
    protected $model = EventSubmissionLog::class;

    public function definition(): array
    {
        return [
            'action' => 'submitted',
        ];
    }
}
