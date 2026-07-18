<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
});

it('keeps event identity in canonical columns', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create([
            'title' => 'Alpha',
            'metadata' => [
                'event' => [
                    'name' => 'Metadata Alpha',
                ],
            ],
        ]);

        expect($event->title)->toBe('Alpha');
        expect($event->metadata)->toBe(['event' => ['name' => 'Metadata Alpha']]);

        $event->update([
            'title' => 'Beta',
        ]);

        expect($event->fresh()->title)->toBe('Beta');
    });
});
