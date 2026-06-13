<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
});

it('keeps display metadata keys tied to the event title', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create([
            'title' => 'Alpha',
            'metadata' => [
                'event' => [
                    'name' => 'Metadata Alpha',
                ],
            ],
        ]);

        expect($event->metadata('event.name'))->toBe('Alpha');
        expect(Event::metadataValue('event.name'))->toBe('Alpha');

        $event->update([
            'title' => 'Beta',
        ]);

        expect($event->fresh()->metadata('event.name'))->toBe('Beta');
        expect(Event::metadataValue('event.name'))->toBe('Beta');
    });
});
