<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Pages;

use AIArmada\Cashier\Actions\ProbeGatewayHealth;
use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\CommerceSupport\Persistence\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

final class GatewayManagement extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    /** @var view-string */
    protected string $view = 'filament-cashier::pages.gateway-management';

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier::gateway.management.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-cashier.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-cashier.pages.navigation_sort.gateway_management');
    }

    public function getTitle(): string
    {
        return __('filament-cashier::gateway.management.title');
    }

    public function getMaxContentWidth(): Width
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
     * @return Collection<int, array{gateway: string, label: string, color: string, icon: string, status: string, statusColor: string, lastCheck: string, message: string|null}>
     */
    public function getGatewayHealth(): Collection
    {
        $detector = $this->getGatewayDetector();
        $gateways = $detector->availableGateways();

        $health = collect($gateways)->map(function (string $gateway) use ($detector) {
            $health = $this->checkGatewayHealth($gateway);

            return [
                'gateway' => $gateway,
                'label' => $detector->getLabel($gateway),
                'color' => $detector->getColor($gateway),
                'icon' => $detector->getIcon($gateway),
                'status' => $health['status'],
                'statusColor' => $health['color'],
                'lastCheck' => CarbonImmutable::now()->format('Y-m-d H:i:s'),
                'message' => $health['message'],
            ];
        })->values();

        return $health;
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

    /** @return array{status: string, color: string, message: string|null} */
    protected function checkGatewayHealth(string $gateway): array
    {
        $status = app(ProbeGatewayHealth::class)->handle($gateway);

        return [
            'status' => $status === 'sdk_missing' ? 'unknown' : $status,
            'color' => match ($status) {
                'healthy' => 'success',
                'error' => 'danger',
                'not_configured' => 'warning',
                default => 'gray',
            },
            'message' => match ($status) {
                'healthy' => null,
                'error' => __('filament-cashier::gateway.health.connection_error'),
                'not_configured' => __('filament-cashier::gateway.health.not_configured'),
                'sdk_missing' => __('filament-cashier::gateway.health.sdk_missing'),
                default => __('filament-cashier::gateway.health.unknown'),
            },
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

}
