<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip;

use AIArmada\FilamentChip\Pages\AnalyticsDashboardPage;
use AIArmada\FilamentChip\Resources\BankAccountResource;
use AIArmada\FilamentChip\Resources\ClientResource;
use AIArmada\FilamentChip\Resources\CompanyStatementResource;
use AIArmada\FilamentChip\Resources\PaymentResource;
use AIArmada\FilamentChip\Resources\PurchaseResource;
use AIArmada\FilamentChip\Resources\SendInstructionResource;
use AIArmada\FilamentChip\Widgets\ChipStatsWidget;
use AIArmada\FilamentChip\Widgets\RecentTransactionsWidget;
use AIArmada\FilamentChip\Widgets\RevenueChartWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Filament CHIP Plugin
 *
 * Provides admin panel integration for CHIP payment gateway data.
 * Resources are grouped by audience: operator (default) and developer.
 */
final class FilamentChipPlugin implements Plugin
{
    private bool $hasOperatorResources = true;

    private bool $hasDeveloperResources = false;

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
        return 'filament-chip';
    }

    /**
     * Enable operator-facing resources (default: transactions, purchases, clients).
     */
    public function operatorResources(bool $enabled = true): static
    {
        $this->hasOperatorResources = $enabled;

        return $this;
    }

    /**
     * Enable developer-facing resources (statements).
     */
    public function developerResources(bool $enabled = true): static
    {
        $this->hasDeveloperResources = $enabled;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages($this->getPages())
            ->resources($this->getResources())
            ->widgets($this->getWidgets());
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * @return array<class-string>
     */
    private function getPages(): array
    {
        return [
            AnalyticsDashboardPage::class,
        ];
    }

    /**
     * @return array<class-string>
     */
    private function getResources(): array
    {
        $resources = [];

        // Operator resources (always available with default config)
        if ($this->hasOperatorResources) {
            $resources[] = PurchaseResource::class;
            $resources[] = ClientResource::class;
            $resources[] = PaymentResource::class;
            $resources[] = SendInstructionResource::class;
            $resources[] = BankAccountResource::class;
        }

        // Developer resources
        if ($this->hasDeveloperResources) {
            $resources[] = CompanyStatementResource::class;
        }

        return $resources;
    }

    /**
     * @return array<class-string>
     */
    private function getWidgets(): array
    {
        return [
            ChipStatsWidget::class,
            RevenueChartWidget::class,
            RecentTransactionsWidget::class,
        ];
    }
}
