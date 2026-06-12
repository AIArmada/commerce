<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventApprovalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventApprovalRequest>
 */
final class EventApprovalRequestFactory extends Factory
{
    protected $model = EventApprovalRequest::class;

    public function definition(): array
    {
        return [
            'status' => 'pending',
        ];
    }
}
