<?php

declare(strict_types=1);

use AIArmada\FilamentDocs\FilamentDocsPlugin;
use AIArmada\FilamentDocs\Resources\DocResource;
use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use AIArmada\FilamentDocs\Widgets\DocStatsWidget;
use Filament\Panel;
use Mockery;

it('exposes a stable plugin id', function (): void {
    $plugin = new FilamentDocsPlugin();

    expect($plugin->getId())->toBe('filament-docs');
});

it('registers docs resources and widgets on the panel', function (): void {
    /** @var Panel&Mockery\MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([DocResource::class, DocTemplateResource::class])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with([DocStatsWidget::class])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentDocsPlugin())->register($panel);
});
