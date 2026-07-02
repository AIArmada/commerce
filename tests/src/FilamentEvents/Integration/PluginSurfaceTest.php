<?php

declare(strict_types=1);

use AIArmada\Events\Enums\EventVisibility;
use AIArmada\Events\States\EventModerationStatus\EventModerationStatus as EventModerationStatusState;
use AIArmada\Events\States\EventStatus\EventStatus as EventStatusState;
use AIArmada\Events\States\OccurrenceStatus\OccurrenceStatus as OccurrenceStatusState;
use AIArmada\Events\States\RegistrationStatus\RegistrationStatus as RegistrationStatusState;
use AIArmada\FilamentEvents\FilamentEventsPlugin;
use AIArmada\FilamentEvents\FilamentEventsServiceProvider;
use AIArmada\FilamentEvents\Pages\ApprovalQueue;
use AIArmada\FilamentEvents\Pages\CheckInConsole;
use AIArmada\FilamentEvents\Pages\EventPublicPreview;
use AIArmada\FilamentEvents\Pages\NotificationCenter;
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
            NotificationCenter::class,
            ApprovalQueue::class,
            EventPublicPreview::class,
        )
        ->and($widgets)->toContain(EventStatsWidget::class);
});

it('loads the filament events package config', function (): void {
    $this->app->register(FilamentEventsServiceProvider::class);

    expect(config('filament-events.navigation.group'))->toBe('Events')
        ->and(config('filament-events.resources.enabled.event'))->toBeTrue()
        ->and(config('filament-events.resources.enabled.registration_participant'))->toBeTrue()
        ->and(config('filament-events.resources.enabled.change_log'))->toBeTrue();
});

it('reads the configured navigation group from pages', function (): void {
    config()->set('filament-events.navigation.group', 'Event Operations');

    foreach ([
        CheckInConsole::class,
        NotificationCenter::class,
        ApprovalQueue::class,
        EventPublicPreview::class,
    ] as $page) {
        expect($page::getNavigationGroup())->toBe('Event Operations');
    }
});

it('exposes the current enum options', function (): void {
    expect(EventStatusState::options())->toMatchArray([
        'draft' => 'Draft',
        'pending_review' => 'Pending Review',
        'published' => 'Published',
    ])
        ->and(EventModerationStatusState::options())->toMatchArray([
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ])
        ->and(EventVisibility::options())->toMatchArray([
            EventVisibility::Public->value => EventVisibility::Public->label(),
            EventVisibility::Unlisted->value => EventVisibility::Unlisted->label(),
            EventVisibility::Private->value => EventVisibility::Private->label(),
        ])
        ->and(OccurrenceStatusState::options())->toMatchArray([
            'draft' => 'Draft',
            'scheduled' => 'Scheduled',
            'published' => 'Published',
            'cancelled' => 'Cancelled',
        ])
        ->and(RegistrationStatusState::options())->toMatchArray([
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'cancelled' => 'Cancelled',
        ]);
});
