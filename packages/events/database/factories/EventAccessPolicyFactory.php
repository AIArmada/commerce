<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventAccessPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAccessPolicy>
 */
final class EventAccessPolicyFactory extends Factory
{
    protected $model = EventAccessPolicy::class;

    public function definition(): array
    {
        return [
            'registration_required' => false,
            'approval_required' => false,
            'payment_required' => false,
            'ticket_required' => false,
            'seating_required' => false,
            'walk_in_allowed' => true,
            'waitlist_enabled' => false,
        ];
    }
}
