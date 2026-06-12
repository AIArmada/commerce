<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventSubmissionAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSubmissionAttachment>
 */
final class EventSubmissionAttachmentFactory extends Factory
{
    protected $model = EventSubmissionAttachment::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word() . '.pdf',
        ];
    }
}
