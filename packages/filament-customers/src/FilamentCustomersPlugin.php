<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentCustomersPlugin implements Plugin
{
    protected bool $hasSegmentRebuildPage = false;

    protected bool $hasAddressValidationPage = false;

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
        return 'filament-customers';
    }

    public function segmentRebuildPage(bool $condition = true): static
    {
        $this->hasSegmentRebuildPage = $condition;

        return $this;
    }

    public function addressValidationPage(bool $condition = true): static
    {
        $this->hasAddressValidationPage = $condition;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                Resources\CustomerResource::class,
                Resources\SegmentResource::class,
            ])
            ->pages($this->getPages())
            ->widgets([
                Widgets\CustomerStatsWidget::class,
                Widgets\RecentCustomersWidget::class,
            ]);
    }

    /**
     * @return array<class-string>
     */
    protected function getPages(): array
    {
        $pages = [];

        if (config('filament-customers.features.merge_customers', true)) {
            $pages[] = Pages\MergeCustomersPage::class;
        }

        if ($this->hasSegmentRebuildPage && config('filament-customers.features.segment_rebuild', false)) {
            $pages[] = Pages\SegmentRebuildPage::class;
        }

        if ($this->hasAddressValidationPage && config('filament-customers.features.address_validation', false)) {
            $pages[] = Pages\AddressValidationPage::class;
        }

        return $pages;
    }

    public function boot(Panel $panel): void
    {
        // Boot logic here
    }
}
