<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Cashier\Cashier;

/**
 * Detects available payment gateways based on installed packages.
 */
final class GatewayDetector
{
    /**
     * Get a collection of available gateway names.
     *
     * @return Collection<int, string>
     */
    public function availableGateways(): Collection
    {
        return collect([
            'stripe' => class_exists(Cashier::class),
            'chip' => $this->isChipAvailable(),
        ])->filter()->keys();
    }

    /**
     * Check if a specific gateway is available.
     */
    public function isAvailable(string $gateway): bool
    {
        return $this->availableGateways()->contains($gateway);
    }

    /**
     * Check if any gateway is available.
     */
    public function hasAnyGateway(): bool
    {
        return $this->availableGateways()->isNotEmpty();
    }

    /**
     * Get configuration for a specific gateway.
     *
     * @return array{label: string, icon: string, color: string, dashboard_url: string}
     */
    public function getGatewayConfig(string $gateway): array
    {
        $defaults = [
            'label' => ucfirst($gateway),
            'icon' => 'heroicon-o-cube',
            'color' => 'gray',
            'dashboard_url' => '#',
        ];

        /** @var array{label: string, icon: string, color: string, dashboard_url: string} $config */
        $config = config("filament-cashier.gateways.{$gateway}", $defaults);

        return array_merge($defaults, $config);
    }

    /**
     * Get display label for a gateway.
     */
    public function getLabel(string $gateway): string
    {
        return $this->getGatewayConfig($gateway)['label'];
    }

    /**
     * Get icon for a gateway.
     */
    public function getIcon(string $gateway): string
    {
        return $this->getGatewayConfig($gateway)['icon'];
    }

    /**
     * Get color for a gateway.
     */
    public function getColor(string $gateway): string
    {
        return $this->getGatewayConfig($gateway)['color'];
    }

    /**
     * Get dashboard URL for a gateway.
     */
    public function getDashboardUrl(string $gateway): string
    {
        return $this->getGatewayConfig($gateway)['dashboard_url'];
    }

    /**
     * Get gateway options for select fields.
     *
     * @return array<string, string>
     */
    public function getGatewayOptions(): array
    {
        return $this->availableGateways()
            ->mapWithKeys(fn (string $gateway) => [
                $gateway => $this->getLabel($gateway),
            ])
            ->toArray();
    }

    private function isChipAvailable(): bool
    {
        if (! class_exists(\AIArmada\CashierChip\Cashier::class)) {
            return false;
        }

        $subscriptionModel = \AIArmada\CashierChip\Cashier::$subscriptionModel ?? null;

        return is_string($subscriptionModel)
            && class_exists($subscriptionModel)
            && is_a($subscriptionModel, Model::class, true);
    }
}
