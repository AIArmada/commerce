<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventModerationAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventModerationAction>
 */
final class EventModerationActionFactory extends Factory
{
    protected $model = EventModerationAction::class;

    public function definition(): array
    {
        return [
            'action_type' => 'warn',
            'status' => 'pending',
        ];
    }
}
