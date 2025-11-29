<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates;

use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource;
use AIArmada\FilamentAffiliates\Widgets\AffiliateStatsWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentAffiliatesPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-affiliates';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                AffiliateResource::class,
                AffiliateConversionResource::class,
                AffiliatePayoutResource::class,
            ])
            ->widgets([
                AffiliateStatsWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
