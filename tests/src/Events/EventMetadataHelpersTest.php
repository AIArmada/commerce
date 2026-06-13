<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
});

it('does not cache stale metadata values across calls', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create([
            'title' => 'Alpha',
        ]);

        expect(Event::metadataValue('event.name'))->toBe('Alpha');

        $event->update([
            'title' => 'Beta',
        ]);

        expect(Event::metadataValue('event.name'))->toBe('Beta');
    });
});
