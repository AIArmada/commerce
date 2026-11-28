<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\CustomerPortal\Pages;

use AIArmada\Cashier\Actions\ChangeCustomerSubscription;
use AIArmada\Cashier\Queries\CustomerSubscriptionsQuery;
use AIArmada\Cashier\Support\UnifiedSubscription;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Throwable;

final class ManageSubscriptions extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCreditCard;

    public int $perGatewayLimit = 50;

    public bool $hasMoreSubscriptions = false;

    private const int DEFAULT_LOAD_MORE_INCREMENT = 50;

    private const int MAX_LOAD_MORE_INCREMENT = 50;

    private const int MAX_PER_GATEWAY_LIMIT = 200;

    /** @var view-string */
    protected string $view = 'filament-cashier::customer-portal.manage-subscriptions';

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier::portal.subscriptions.title');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-cashier.billing_portal.navigation_sort.subscriptions');
    }

    public function getTitle(): string
    {
        return __('filament-cashier::portal.subscriptions.title');
    }

    /**
     * @return Collection<int, UnifiedSubscription>
     */
    public function getSubscriptions(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof Model) {
            $this->hasMoreSubscriptions = false;

            return collect();
        }

        $query = app(CustomerSubscriptionsQuery::class);
        $result = $query->getForUser($user, $this->perGatewayLimit, fetchExtra: true);

        $this->hasMoreSubscriptions = $result['hasMore'];

        return $result['items'];
    }

    public function loadMoreSubscriptions(int $increment = self::DEFAULT_LOAD_MORE_INCREMENT): void
    {
        $this->perGatewayLimit = min(
            self::MAX_PER_GATEWAY_LIMIT,
            $this->perGatewayLimit + min(max(1, $increment), self::MAX_LOAD_MORE_INCREMENT),
        );
    }

    public function cancelSubscription(string $gateway, string $id): void
    {
        $this->changeSubscription($gateway, $id, 'cancel');
    }

    public function resumeSubscription(string $gateway, string $id): void
    {
        $this->changeSubscription($gateway, $id, 'resume');
    }

    private function changeSubscription(string $gateway, string $id, string $operation): void
    {
        $user = auth()->user();

        if (! $user instanceof Model) {
            return;
        }

        try {
            $action = app(ChangeCustomerSubscription::class);

            if ($operation === 'cancel') {
                $action->cancel($user, $gateway, $id);
            } else {
                $action->resume($user, $gateway, $id);
            }

            Notification::make()
                ->title(__('filament-cashier::portal.subscriptions.' . $operation . '_success'))
                ->success()
                ->send();
        } catch (AuthorizationException) {
            Notification::make()
                ->title(__('filament-cashier::portal.subscriptions.unauthorized'))
                ->danger()
                ->send();
        } catch (Throwable) {
            Notification::make()
                ->title(__('filament-cashier::portal.subscriptions.' . $operation . '_error'))
                ->danger()
                ->send();
        }
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('new_subscription')
                ->label(__('filament-cashier::portal.subscriptions.new'))
                ->icon('heroicon-o-plus')
                ->url(function (): ?string {
                    $panelId = (string) config('filament-cashier.billing_portal.panel_id', 'billing');
                    $routeName = "filament.{$panelId}.resources.unified-subscriptions.create";

                    if (! Route::has($routeName)) {
                        return null;
                    }

                    return route($routeName);
                })
                ->visible(fn () => config('filament-cashier.billing_portal.features.subscriptions', true)),
        ];
    }
}
