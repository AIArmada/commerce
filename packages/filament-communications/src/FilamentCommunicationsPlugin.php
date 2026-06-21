<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications;

use AIArmada\Filament\Communications\Resources\CommunicationBatchResource;
use AIArmada\Filament\Communications\Resources\CommunicationDeliveryResource;
use AIArmada\Filament\Communications\Resources\CommunicationPreferenceResource;
use AIArmada\Filament\Communications\Resources\CommunicationResource;
use AIArmada\Filament\Communications\Resources\CommunicationSuppressionResource;
use AIArmada\Filament\Communications\Resources\CommunicationTemplateResource;
use AIArmada\Filament\Communications\Resources\CommunicationThreadResource;
use AIArmada\Filament\Communications\Widgets\DeliveryStatusOverviewWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentCommunicationsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        // @phpstan-ignore return.type
        return filament(app(self::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-communications';
    }

    public function register(Panel $panel): void
    {
        $resources = $this->getResources();

        if ($resources !== []) {
            $panel->resources($resources);
        }

        $panel->widgets([
            DeliveryStatusOverviewWidget::class,
        ]);
    }

    public function boot(Panel $panel): void {}

    private function getResources(): array
    {
        $resources = [];

        if (config('filament-communications.resources.communications.enabled', true)) {
            $resources[] = CommunicationResource::class;
        }

        if (config('filament-communications.resources.deliveries.enabled', true)) {
            $resources[] = CommunicationDeliveryResource::class;
        }

        if (config('filament-communications.resources.threads.enabled', true)) {
            $resources[] = CommunicationThreadResource::class;
        }

        if (config('filament-communications.resources.templates.enabled', true)) {
            $resources[] = CommunicationTemplateResource::class;
        }

        if (config('filament-communications.resources.preferences.enabled', true)) {
            $resources[] = CommunicationPreferenceResource::class;
        }

        if (config('filament-communications.resources.suppressions.enabled', true)) {
            $resources[] = CommunicationSuppressionResource::class;
        }

        if (config('filament-communications.resources.batches.enabled', true)) {
            $resources[] = CommunicationBatchResource::class;
        }

        return $resources;
    }
}
