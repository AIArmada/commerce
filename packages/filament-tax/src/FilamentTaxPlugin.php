<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentTaxPlugin implements Plugin
{
    protected bool $hasZones = true;

    protected bool $hasClasses = true;

    protected bool $hasRates = true;

    protected bool $hasExemptions = true;

    protected bool $hasWidgets = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-tax';
    }

    /**
     * Enable/disable Tax Zones resource.
     */
    public function zones(bool $condition = true): static
    {
        $this->hasZones = $condition;

        return $this;
    }

    /**
     * Enable/disable Tax Classes resource.
     */
    public function classes(bool $condition = true): static
    {
        $this->hasClasses = $condition;

        return $this;
    }

    /**
     * Enable/disable Tax Rates resource.
     */
    public function rates(bool $condition = true): static
    {
        $this->hasRates = $condition;

        return $this;
    }

    /**
     * Enable/disable Tax Exemptions resource.
     */
    public function exemptions(bool $condition = true): static
    {
        $this->hasExemptions = $condition;

        return $this;
    }

    /**
     * Enable/disable dashboard widgets.
     */
    public function widgets(bool $condition = true): static
    {
        $this->hasWidgets = $condition;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $resources = [];
        $widgets = [];
        $pages = [];

        if ($this->hasZones) {
            $resources[] = Resources\TaxZoneResource::class;
        }

        if ($this->hasClasses) {
            $resources[] = Resources\TaxClassResource::class;
        }

        if ($this->hasRates) {
            $resources[] = Resources\TaxRateResource::class;
        }

        if ($this->hasExemptions) {
            $resources[] = Resources\TaxExemptionResource::class;
        }

        if ($this->hasWidgets) {
            $widgets[] = Widgets\TaxStatsWidget::class;
            $widgets[] = Widgets\ExpiringExemptionsWidget::class;
            $widgets[] = Widgets\ZoneCoverageWidget::class;
        }

        // Only register settings page if spatie settings plugin is available
        if (class_exists(\Filament\Pages\SettingsPage::class)) {
            $pages[] = Pages\ManageTaxSettings::class;
        }

        $panel
            ->resources($resources)
            ->widgets($widgets)
            ->pages($pages);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
