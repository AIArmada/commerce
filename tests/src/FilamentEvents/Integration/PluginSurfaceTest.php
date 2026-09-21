<?php

declare(strict_types=1);

use AIArmada\FilamentEvents\FilamentEventsPlugin;
use AIArmada\FilamentEvents\Pages\ApprovalQueue;
use AIArmada\FilamentEvents\Pages\CheckInConsole;
use AIArmada\FilamentEvents\Pages\EventPublicPreview;
use AIArmada\FilamentEvents\Resources\EventChangeLogResource;
use AIArmada\FilamentEvents\Resources\EventRegistrationParticipantResource;
use AIArmada\FilamentEvents\Resources\EventResource;
use AIArmada\FilamentEvents\Widgets\EventStatsWidget;

it('exposes the plugin resources, pages, and widgets', function (): void {
    $plugin = FilamentEventsPlugin::make();

    $resources = (new ReflectionMethod($plugin, 'getResources'))->invoke($plugin);
    $pages = (new ReflectionMethod($plugin, 'getPages'))->invoke($plugin);
    $widgets = (new ReflectionMethod($plugin, 'getWidgets'))->invoke($plugin);

    expect($plugin->getId())->toBe('filament-events')
        ->and($resources)->toContain(
            EventResource::class,
            EventRegistrationParticipantResource::class,
            EventChangeLogResource::class,
        )
        ->and($pages)->toContain(
            CheckInConsole::class,
            ApprovalQueue::class,
            EventPublicPreview::class,
        )
        ->and($widgets)->toContain(EventStatsWidget::class);
});

it('reads the configured navigation group from pages', function (): void {
    config()->set('filament-events.navigation.group', 'Event Operations');

    foreach ([
        CheckInConsole::class,
        ApprovalQueue::class,
        EventPublicPreview::class,
    ] as $page) {
        expect($page::getNavigationGroup())->toBe('Event Operations');
    }
});
