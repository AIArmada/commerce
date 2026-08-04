<?php

declare(strict_types=1);

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventMedia;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer;

it('registers the default media profile for each event scope', function (): void {
    $event = new Event;
    $occurrence = new EventOccurrence;
    $session = new EventSession;

    expect(collect($event->getRegisteredMediaCollections())->pluck('name')->all())
        ->toBe(['cover', 'poster', 'gallery'])
        ->and(collect($occurrence->getRegisteredMediaCollections())->pluck('name')->all())
        ->toBe(['cover'])
        ->and(collect($session->getRegisteredMediaCollections())->pluck('name')->all())
        ->toBe(['cover']);
});

it('registers configured conversions against their media collections', function (): void {
    config()->set('media-library.image_optimizers', []);
    config()->set('media-library.file_namer', DefaultFileNamer::class);

    $occurrence = new EventOccurrence;

    $occurrence->registerMediaConversions();

    expect($occurrence->mediaConversions)->toHaveCount(1)
        ->and($occurrence->mediaConversions[0]->getName())->toBe('thumb')
        ->and($occurrence->mediaConversions[0]->getPerformOnCollections())->toBe(['cover']);
});

it('allows applications to replace an event media profile', function (): void {
    config()->set('events.media.profiles.session', [
        'collections' => [
            'artwork' => [
                'mimes' => ['image/svg+xml'],
                'single_file' => true,
            ],
        ],
        'conversions' => [],
    ]);

    $session = new EventSession;

    expect(collect($session->getRegisteredMediaCollections())->pluck('name')->all())
        ->toBe(['artwork'])
        ->and($session->mediaConversions)->toBe([]);
});

it('keeps structured event media separate from Spatie media', function (): void {
    $occurrence = new EventOccurrence;
    $session = new EventSession;

    expect(get_class($occurrence->mediaRecords()->getRelated()))
        ->toBe(EventMedia::class)
        ->and(get_class($session->mediaRecords()->getRelated()))
        ->toBe(EventMedia::class);
});
