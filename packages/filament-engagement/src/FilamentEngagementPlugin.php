<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement;

use AIArmada\FilamentEngagement\Resources\BookmarkCollectionResource;
use AIArmada\FilamentEngagement\Resources\BookmarkResource;
use AIArmada\FilamentEngagement\Resources\FollowResource;
use AIArmada\FilamentEngagement\Resources\ReactionResource;
use AIArmada\FilamentEngagement\Resources\ReminderResource;
use AIArmada\FilamentEngagement\Resources\ResponseResource;
use AIArmada\FilamentEngagement\Resources\SubscriptionResource;
use AIArmada\FilamentEngagement\Widgets\EngagementOverviewWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentEngagementPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /* @phpstan-ignore return.type */
        return filament(app(self::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-engagement';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources($this->getResources())
            ->widgets($this->getWidgets());
    }

    public function boot(Panel $panel): void {}

    private function getResources(): array
    {
        $resources = [];
        $enabled = config('filament-engagement.resources.enabled', []);

        if ($enabled['follow'] ?? true) {
            $resources[] = FollowResource::class;
        }
        if ($enabled['bookmark'] ?? true) {
            $resources[] = BookmarkResource::class;
        }
        if ($enabled['collection'] ?? true) {
            $resources[] = BookmarkCollectionResource::class;
        }
        if ($enabled['response'] ?? true) {
            $resources[] = ResponseResource::class;
        }
        if ($enabled['reaction'] ?? true) {
            $resources[] = ReactionResource::class;
        }
        if ($enabled['subscription'] ?? true) {
            $resources[] = SubscriptionResource::class;
        }
        if ($enabled['reminder'] ?? true) {
            $resources[] = ReminderResource::class;
        }

        return $resources;
    }

    private function getWidgets(): array
    {
        return [EngagementOverviewWidget::class];
    }
}
