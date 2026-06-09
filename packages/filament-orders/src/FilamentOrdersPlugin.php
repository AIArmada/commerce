<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentOrdersPlugin implements Plugin
{
    private bool $hasTimelinePage = false;

    private bool $hasFulfillmentPage = false;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-orders';
    }

    public function timelinePage(bool $condition = true): static
    {
        $this->hasTimelinePage = $condition;

        return $this;
    }

    public function fulfillmentPage(bool $condition = true): static
    {
        $this->hasFulfillmentPage = $condition;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $pages = [];

        if ($this->hasTimelinePage && config('filament-orders.pages.timeline', true)) {
            $pages[] = Pages\OrderTimelinePage::class;
        }

        if ($this->hasFulfillmentPage && config('filament-orders.pages.fulfillment', true)) {
            $pages[] = Pages\OrderFulfillmentPage::class;
        }

        $panel
            ->resources([
                Resources\OrderResource::class,
            ])
            ->pages($pages)
            ->widgets([
                Widgets\OrderStatsWidget::class,
                Widgets\RecentOrdersWidget::class,
                Widgets\OrderStatusDistributionWidget::class,
            ]);
    }

    public function boot(Panel $panel): void {}
}
