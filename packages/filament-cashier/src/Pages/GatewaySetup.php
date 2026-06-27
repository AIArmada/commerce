<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Laravel\Cashier\Cashier;

final class GatewaySetup extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    /** @var view-string */
    protected string $view = 'filament-cashier::pages.gateway-setup';

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier::gateway.setup.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-cashier.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-cashier.pages.navigation_sort.gateway_setup');
    }

    public function getTitle(): string
    {
        return __('filament-cashier::gateway.setup.title');
    }

    /**
     * @return array<array{name: string, description: string, install: string, available: bool}>
     */
    public function getGateways(): array
    {
        return [
            [
                'name' => __('filament-cashier::gateway.setup.stripe.name'),
                'description' => __('filament-cashier::gateway.setup.stripe.description'),
                'install' => __('filament-cashier::gateway.setup.stripe.install'),
                'available' => class_exists(Cashier::class),
            ],
            [
                'name' => __('filament-cashier::gateway.setup.chip.name'),
                'description' => __('filament-cashier::gateway.setup.chip.description'),
                'install' => __('filament-cashier::gateway.setup.chip.install'),
                'available' => class_exists(\AIArmada\CashierChip\Billing\Cashier::class),
            ],
        ];
    }
}
