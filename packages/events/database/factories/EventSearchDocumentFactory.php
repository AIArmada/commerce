<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventSearchDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSearchDocument>
 */
final class EventSearchDocumentFactory extends Factory
{
    protected $model = EventSearchDocument::class;

    public function definition(): array
    {
        return [
            'document_type' => 'event',
            'status' => 'indexed',
        ];
    }
}
