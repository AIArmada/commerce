<?php

declare(strict_types=1);

use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\EventVisibility;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\FilamentEvents\FilamentEventsPlugin;
use AIArmada\FilamentEvents\FilamentEventsServiceProvider;
use AIArmada\FilamentEvents\Pages\ApprovalQueue;
use AIArmada\FilamentEvents\Pages\CheckInConsole;
use AIArmada\FilamentEvents\Pages\EventPublicPreview;
use AIArmada\FilamentEvents\Pages\NotificationCenter;
use AIArmada\FilamentEvents\Pages\SeatMapManager;
use AIArmada\FilamentEvents\Widgets\EventStatsWidget;

it('exposes the plugin resources, pages, and widgets', function (): void {
    $plugin = FilamentEventsPlugin::make();

    $resources = (new \ReflectionMethod($plugin, 'getResources'))->invoke($plugin);
    $pages = (new \ReflectionMethod($plugin, 'getPages'))->invoke($plugin);
    $widgets = (new \ReflectionMethod($plugin, 'getWidgets'))->invoke($plugin);

    expect($plugin->getId())->toBe('filament-events')
        ->and($resources)->toContain(
            \AIArmada\FilamentEvents\Resources\EventResource::class,
        )
        ->and($pages)->toContain(
            CheckInConsole::class,
            NotificationCenter::class,
            ApprovalQueue::class,
            EventPublicPreview::class,
            SeatMapManager::class,
        )
        ->and($widgets)->toContain(EventStatsWidget::class);
});

it('loads the filament events package config', function (): void {
    $this->app->register(FilamentEventsServiceProvider::class);

    expect(config('filament-events.navigation.group'))->toBe('Events')
        ->and(config('filament-events.resources.enabled.event'))->toBeTrue()
        ->and(config('filament-events.resources.enabled.change_log'))->toBeTrue();
});

it('exposes the current enum options', function (): void {
    expect(EventStatus::options())->toMatchArray([
        EventStatus::Draft->value => EventStatus::Draft->label(),
        EventStatus::PendingReview->value => EventStatus::PendingReview->label(),
        EventStatus::Published->value => EventStatus::Published->label(),
    ])
        ->and(EventModerationStatus::options())->toMatchArray([
            EventModerationStatus::Pending->value => EventModerationStatus::Pending->label(),
            EventModerationStatus::Approved->value => EventModerationStatus::Approved->label(),
            EventModerationStatus::Rejected->value => EventModerationStatus::Rejected->label(),
        ])
        ->and(EventVisibility::options())->toMatchArray([
            EventVisibility::Public->value => EventVisibility::Public->label(),
            EventVisibility::Unlisted->value => EventVisibility::Unlisted->label(),
            EventVisibility::Private->value => EventVisibility::Private->label(),
        ])
        ->and(OccurrenceStatus::options())->toMatchArray([
            OccurrenceStatus::Draft->value => OccurrenceStatus::Draft->label(),
            OccurrenceStatus::Scheduled->value => OccurrenceStatus::Scheduled->label(),
            OccurrenceStatus::Published->value => OccurrenceStatus::Published->label(),
            OccurrenceStatus::Cancelled->value => OccurrenceStatus::Cancelled->label(),
        ])
        ->and(RegistrationStatus::options())->toMatchArray([
            RegistrationStatus::Pending->value => RegistrationStatus::Pending->label(),
            RegistrationStatus::Confirmed->value => RegistrationStatus::Confirmed->label(),
            RegistrationStatus::Cancelled->value => RegistrationStatus::Cancelled->label(),
        ]);
});
