<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Cashier\Cashier;

final class GatewayDetector
{
    /**
     * @return Collection<int, string>
     */
    public function availableGateways(): Collection
    {
        return collect([
            'stripe' => class_exists(Cashier::class),
            'chip' => $this->isChipAvailable(),
        ])->filter()->keys();
    }

    public function isAvailable(string $gateway): bool
    {
        return $this->availableGateways()->contains($gateway);
    }

    public function hasAnyGateway(): bool
    {
        return $this->availableGateways()->isNotEmpty();
    }

    /**
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
        $config = config("cashier.gateways.{$gateway}", $defaults);

        return array_merge($defaults, $config);
    }

    public function getLabel(string $gateway): string
    {
        return $this->getGatewayConfig($gateway)['label'];
    }

    public function getIcon(string $gateway): string
    {
        return $this->getGatewayConfig($gateway)['icon'];
    }

    public function getColor(string $gateway): string
    {
        return $this->getGatewayConfig($gateway)['color'];
    }

    public function getDashboardUrl(string $gateway): string
    {
        return $this->getGatewayConfig($gateway)['dashboard_url'];
    }

    /**
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

        $subscriptionModel = \AIArmada\CashierChip\Cashier::$subscriptionModel;

        return class_exists($subscriptionModel)
            && is_a($subscriptionModel, Model::class, true);
    }
}
