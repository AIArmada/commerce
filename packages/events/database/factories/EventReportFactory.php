<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventReport>
 */
final class EventReportFactory extends Factory
{
    protected $model = EventReport::class;

    public function definition(): array
    {
        return [
            'report_type' => 'spam',
            'status' => 'open',
            'severity' => 'low',
            'reported_at' => now(),
        ];
    }
}
