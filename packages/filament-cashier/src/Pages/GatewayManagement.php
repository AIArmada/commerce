<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Pages;

use AIArmada\Chip\Chip;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCashier\FilamentCashierPlugin;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use BackedEnum;
use Exception;
use Filament\Facades\Filament;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Stripe\Account;
use Stripe\Stripe;

final class GatewayManagement extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament-cashier::pages.gateway-management';

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier::gateway.management.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return FilamentCashierPlugin::get()->getNavigationGroup();
    }

    public function getTitle(): string
    {
        return __('filament-cashier::gateway.management.title');
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    public function getGatewayDetector(): GatewayDetector
    {
        return app(GatewayDetector::class);
    }

    /**
     * Get gateway health status.
     *
     * @return Collection<int, array{gateway: string, label: string, color: string, icon: string, status: string, statusColor: string, lastCheck: string|null, message: string|null}>
     */
    public function getGatewayHealth(): Collection
    {
        $detector = $this->getGatewayDetector();
        $gateways = $detector->availableGateways();

        return collect($gateways)->map(function (string $gateway) use ($detector) {
            $health = $this->checkGatewayHealth($gateway);

            return [
                'gateway' => $gateway,
                'label' => $detector->getLabel($gateway),
                'color' => $detector->getColor($gateway),
                'icon' => $detector->getIcon($gateway),
                'status' => $health['status'],
                'statusColor' => $health['color'],
                'lastCheck' => now()->format('Y-m-d H:i:s'),
                'message' => $health['message'],
            ];
        });
    }

    /**
     * Get default gateway.
     */
    public function getDefaultGateway(): ?string
    {
        $cached = OwnerCache::get(
            OwnerContext::resolve(),
            $this->getDefaultGatewayCacheKey(),
        );

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return config('cashier.default');
    }

    /**
     * Test gateway connection action.
     */
    public function testConnectionAction(): Action
    {
        return Action::make('testConnection')
            ->label(__('filament-cashier::gateway.actions.test_connection'))
            ->icon('heroicon-o-signal')
            ->form([
                Forms\Components\Select::make('gateway')
                    ->label(__('filament-cashier::gateway.fields.gateway'))
                    ->options($this->getGatewayDetector()->getGatewayOptions())
                    ->required(),
            ])
            ->action(function (array $data): void {
                $gateway = $data['gateway'] ?? null;

                if (! is_string($gateway) || ! $this->getGatewayDetector()->isAvailable($gateway)) {
                    Notification::make()
                        ->danger()
                        ->title(__('filament-cashier::gateway.notifications.connection_failed', ['gateway' => __('filament-cashier::gateway.fields.gateway')]))
                        ->body(__('filament-cashier::gateway.health.unknown'))
                        ->send();

                    return;
                }

                $health = $this->checkGatewayHealth($gateway);
                $label = $this->getGatewayDetector()->getLabel($gateway);

                if ($health['status'] === 'healthy') {
                    Notification::make()
                        ->success()
                        ->title(__('filament-cashier::gateway.notifications.connection_success', ['gateway' => $label]))
                        ->send();
                } else {
                    Notification::make()
                        ->danger()
                        ->title(__('filament-cashier::gateway.notifications.connection_failed', ['gateway' => $label]))
                        ->body($health['message'])
                        ->send();
                }
            });
    }

    /**
     * Set default gateway action.
     */
    public function setDefaultAction(): Action
    {
        return Action::make('setDefault')
            ->label(__('filament-cashier::gateway.actions.set_default'))
            ->icon('heroicon-o-star')
            ->form([
                Forms\Components\Select::make('gateway')
                    ->label(__('filament-cashier::gateway.fields.gateway'))
                    ->options($this->getGatewayDetector()->getGatewayOptions())
                    ->default($this->getDefaultGateway())
                    ->required(),
            ])
            ->action(function (array $data): void {
                $gateway = $data['gateway'] ?? null;

                if (! is_string($gateway) || ! $this->getGatewayDetector()->isAvailable($gateway)) {
                    Notification::make()
                        ->danger()
                        ->title(__('filament-cashier::gateway.notifications.connection_failed', ['gateway' => __('filament-cashier::gateway.fields.gateway')]))
                        ->body(__('filament-cashier::gateway.health.unknown'))
                        ->send();

                    return;
                }

                // Store in cache for runtime configuration
                OwnerCache::put(
                    OwnerContext::resolve(),
                    $this->getDefaultGatewayCacheKey(),
                    $gateway,
                );

                Notification::make()
                    ->success()
                    ->title(__('filament-cashier::gateway.notifications.default_set', [
                        'gateway' => $this->getGatewayDetector()->getLabel($gateway),
                    ]))
                    ->send();
            });
    }

    /**
     * Check health of a specific gateway.
     *
     * @return array{status: string, color: string, message: string|null}
     */
    protected function checkGatewayHealth(string $gateway): array
    {
        try {
            if ($gateway === 'stripe') {
                return $this->checkStripeHealth();
            }

            if ($gateway === 'chip') {
                return $this->checkChipHealth();
            }

            return [
                'status' => 'unknown',
                'color' => 'gray',
                'message' => __('filament-cashier::gateway.health.unknown'),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'color' => 'danger',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Stripe API health.
     *
     * @return array{status: string, color: string, message: string|null}
     */
    protected function checkStripeHealth(): array
    {
        $secret = $this->resolveStripeSecret();

        if ($secret === null || $this->isPlaceholderSecret($secret)) {
            return [
                'status' => 'not_configured',
                'color' => 'warning',
                'message' => __('filament-cashier::gateway.health.not_configured'),
            ];
        }

        try {
            if (class_exists(Stripe::class)) {
                $previousApiKey = Stripe::getApiKey();
                Stripe::setApiKey($secret);

                try {
                    Account::retrieve();
                } finally {
                    Stripe::setApiKey(is_string($previousApiKey) ? $previousApiKey : '');
                }

                return [
                    'status' => 'healthy',
                    'color' => 'success',
                    'message' => null,
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'color' => 'danger',
                'message' => $e->getMessage(),
            ];
        }

        return [
            'status' => 'unknown',
            'color' => 'gray',
            'message' => __('filament-cashier::gateway.health.sdk_missing'),
        ];
    }

    /**
     * Check CHIP API health.
     *
     * @return array{status: string, color: string, message: string|null}
     */
    protected function checkChipHealth(): array
    {
        $brandId = config('chip.brand_id') ?? config('cashier.gateways.chip.brand_id');
        $apiKey = config('chip.api_key') ?? config('chip.collect.api_key');

        if (! is_string($brandId) || $brandId === '' || ! is_string($apiKey) || $apiKey === '') {
            return [
                'status' => 'not_configured',
                'color' => 'warning',
                'message' => __('filament-cashier::gateway.health.not_configured'),
            ];
        }

        try {
            if (class_exists(Chip::class)) {
                $chip = app(Chip::class);
                // Simple health check - get brands
                $chip->brands()->first();

                return [
                    'status' => 'healthy',
                    'color' => 'success',
                    'message' => null,
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'color' => 'danger',
                'message' => $e->getMessage(),
            ];
        }

        return [
            'status' => 'unknown',
            'color' => 'gray',
            'message' => __('filament-cashier::gateway.health.sdk_missing'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->testConnectionAction(),
            $this->setDefaultAction(),
        ];
    }

    private function getDefaultGatewayCacheKey(): string
    {
        $panelId = Filament::getCurrentPanel()?->getId() ?? 'default';

        return 'filament-cashier.default_gateway.' . $panelId;
    }

    private function resolveStripeSecret(): ?string
    {
        $serviceSecret = config('services.stripe.secret');

        if (is_string($serviceSecret) && $serviceSecret !== '') {
            return $serviceSecret;
        }

        $cashierSecret = config('cashier.gateways.stripe.secret');

        if (is_string($cashierSecret) && $cashierSecret !== '') {
            return $cashierSecret;
        }

        return null;
    }

    private function isPlaceholderSecret(string $secret): bool
    {
        return str_contains($secret, 'xxx') || str_contains($secret, 'placeholder');
    }
}
