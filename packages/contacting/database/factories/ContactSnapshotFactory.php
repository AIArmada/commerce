<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Database\Factories;

use AIArmada\Contacting\Models\ContactSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactSnapshot>
 */
final class ContactSnapshotFactory extends Factory
{
    protected $model = ContactSnapshot::class;

    public function definition(): array
    {
        return [
            'snapshot_type' => 'contact_method',
            'reason' => 'test',
            'channel' => 'email',
            'value' => 'admin@example.com',
            'payload' => [],
            'metadata' => [],
        ];
    }
}
