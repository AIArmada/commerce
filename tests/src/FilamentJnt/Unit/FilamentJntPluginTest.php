<?php

declare(strict_types=1);

use AIArmada\FilamentJnt\FilamentJntPlugin;
use AIArmada\FilamentJnt\Resources\JntOrderResource;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource;
use AIArmada\FilamentJnt\Widgets\JntStatsWidget;
use Filament\Panel;
use Mockery;

it('exposes a stable plugin id', function (): void {
    expect((new FilamentJntPlugin())->getId())->toBe('filament-jnt');
});

it('registers JNT resources and widgets', function (): void {
    /** @var Panel&Mockery\MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([
            JntOrderResource::class,
            JntTrackingEventResource::class,
            JntWebhookLogResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with([JntStatsWidget::class])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentJntPlugin())->register($panel);
});
