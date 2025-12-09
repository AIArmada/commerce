<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier;

use AIArmada\FilamentCashier\Pages\BillingDashboard;
use AIArmada\FilamentCashier\Pages\GatewayManagement;
use AIArmada\FilamentCashier\Pages\GatewaySetup;
use AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use AIArmada\FilamentCashier\Widgets\GatewayBreakdownWidget;
use AIArmada\FilamentCashier\Widgets\GatewayComparisonWidget;
use AIArmada\FilamentCashier\Widgets\TotalMrrWidget;
use AIArmada\FilamentCashier\Widgets\TotalSubscribersWidget;
use AIArmada\FilamentCashier\Widgets\UnifiedChurnWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentCashierPlugin implements Plugin
{
    private bool $enableDashboard = true;

    private bool $enableSubscriptions = true;

    private bool $enableInvoices = true;

    private bool $enableGatewayManagement = false;

    private bool $customerPortalMode = false;

    private ?string $navigationGroup = null;

    private ?int $navigationSort = null;

    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(self::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-cashier';
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function enableDashboard(bool $enable = true): static
    {
        $this->enableDashboard = $enable;

        return $this;
    }

    public function enableSubscriptions(bool $enable = true): static
    {
        $this->enableSubscriptions = $enable;

        return $this;
    }

    public function enableInvoices(bool $enable = true): static
    {
        $this->enableInvoices = $enable;

        return $this;
    }

    public function enableGatewayManagement(bool $enable = true): static
    {
        $this->enableGatewayManagement = $enable;

        return $this;
    }

    public function customerPortalMode(bool $enable = true): static
    {
        $this->customerPortalMode = $enable;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup ?? config('filament-cashier.navigation.group', 'Billing');
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort ?? config('filament-cashier.navigation.sort', 50);
    }

    public function register(Panel $panel): void
    {
        $gateways = app(GatewayDetector::class)->availableGateways();

        if ($gateways->isEmpty()) {
            $panel->pages([GatewaySetup::class]);

            return;
        }

        $resources = [];
        $pages = [];
        $widgets = [];

        if ($this->enableSubscriptions) {
            $resources[] = UnifiedSubscriptionResource::class;
        }

        if ($this->enableInvoices) {
            $resources[] = UnifiedInvoiceResource::class;
        }

        if ($this->enableDashboard && ! $this->customerPortalMode) {
            $pages[] = BillingDashboard::class;
            $widgets = [
                TotalMrrWidget::class,
                TotalSubscribersWidget::class,
                GatewayBreakdownWidget::class,
                GatewayComparisonWidget::class,
                UnifiedChurnWidget::class,
            ];
        }

        if ($this->enableGatewayManagement && ! $this->customerPortalMode) {
            $pages[] = GatewayManagement::class;
        }

        if ($resources !== []) {
            $panel->resources($resources);
        }

        if ($pages !== []) {
            $panel->pages($pages);
        }

        if ($widgets !== []) {
            $panel->widgets($widgets);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
