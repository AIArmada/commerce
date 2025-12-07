<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentShippingPlugin implements Plugin
{
    protected bool $hasShipmentResource = true;

    protected bool $hasShippingZoneResource = true;

    protected bool $hasShippingRateResource = true;

    protected bool $hasReturnAuthorizationResource = true;

    protected bool $hasDashboardWidgets = true;

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

        if ($this->hasDashboardWidgets) {
            $widgets[] = Widgets\ShippingDashboardWidget::class;
            $widgets[] = Widgets\PendingShipmentsWidget::class;
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
}
