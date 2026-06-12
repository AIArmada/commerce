<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventVerification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventVerification>
 */
final class EventVerificationFactory extends Factory
{
    protected $model = EventVerification::class;

    public function definition(): array
    {
        return [
            'verification_type' => 'manual',
            'status' => 'pending',
        ];
    }
}
