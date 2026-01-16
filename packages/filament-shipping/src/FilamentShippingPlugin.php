<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Throwable;

class FilamentShippingPlugin implements Plugin
{
    protected bool $hasShipmentResource = true;

    protected bool $hasShippingZoneResource = true;

    protected bool $hasReturnAuthorizationResource = true;

    protected bool $hasDashboardWidgets = true;

    protected bool $hasShippingDashboard = true;

    protected bool $hasManifestPage = true;

    protected bool $hasFulfillmentQueue = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-shipping';
    }

    public function register(Panel $panel): void
    {
        $resources = [];
        $pages = [];
        $widgets = [];

        if ($this->hasShipmentResource) {
            $resources[] = Resources\ShipmentResource::class;
        }

        if ($this->hasShippingZoneResource) {
            $resources[] = Resources\ShippingZoneResource::class;
        }

        if ($this->hasReturnAuthorizationResource) {
            $resources[] = Resources\ReturnAuthorizationResource::class;
        }

        if ($this->hasShippingDashboard) {
            $pages[] = Pages\ShippingDashboard::class;
        }

        if ($this->hasManifestPage) {
            $pages[] = Pages\ManifestPage::class;
        }

        if ($this->hasFulfillmentQueue && $this->isFeatureEnabled('enable_fulfillment_queue', true)) {
            $pages[] = Pages\FulfillmentQueue::class;
        }

        if ($this->hasDashboardWidgets) {
            $widgets[] = Widgets\ShippingDashboardWidget::class;
            $widgets[] = Widgets\PendingShipmentsWidget::class;
            $widgets[] = Widgets\CarrierPerformanceWidget::class;
            $widgets[] = Widgets\PendingActionsWidget::class;
        }

        $panel
            ->resources($resources)
            ->pages($pages)
            ->widgets($widgets);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function shipmentResource(bool $condition = true): static
    {
        $this->hasShipmentResource = $condition;

        return $this;
    }

    public function shippingZoneResource(bool $condition = true): static
    {
        $this->hasShippingZoneResource = $condition;

        return $this;
    }

    public function returnAuthorizationResource(bool $condition = true): static
    {
        $this->hasReturnAuthorizationResource = $condition;

        return $this;
    }

    public function dashboardWidgets(bool $condition = true): static
    {
        $this->hasDashboardWidgets = $condition;

        return $this;
    }

    public function shippingDashboard(bool $condition = true): static
    {
        $this->hasShippingDashboard = $condition;

        return $this;
    }

    public function manifestPage(bool $condition = true): static
    {
        $this->hasManifestPage = $condition;

        return $this;
    }

    public function fulfillmentQueue(bool $condition = true): static
    {
        $this->hasFulfillmentQueue = $condition;

        return $this;
    }

    /**
     * Check if a feature is enabled in config, with safe fallback for tests.
     */
    protected function isFeatureEnabled(string $key, bool $default = true): bool
    {
        try {
            return (bool) config("filament-shipping.features.{$key}", $default);
        } catch (Throwable) {
            return $default;
        }
    }
}
