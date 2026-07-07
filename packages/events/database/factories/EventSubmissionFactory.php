<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSubmission>
 */
class EventSubmissionFactory extends Factory
{
    protected $model = EventSubmission::class;

    public function definition(): array
    {
        return [
            'status' => 'pending',
            'submitted_at' => now(),
        ];
    }
}
