<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\CustomerPortal\Pages;

use AIArmada\Cashier\Actions\ManageCustomerPaymentMethod;
use AIArmada\Cashier\Queries\CustomerPaymentMethodsQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Throwable;

final class ManagePaymentMethods extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBanknotes;

    /** @var view-string */
    protected string $view = 'filament-cashier::customer-portal.manage-payment-methods';

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier::portal.payment_methods.title');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-cashier.billing_portal.navigation_sort.payment_methods');
    }

    public function getTitle(): string
    {
        return __('filament-cashier::portal.payment_methods.title');
    }

    /**
     * @return array<string, Collection<int, mixed>>
     */
    public function getPaymentMethods(): array
    {
        $user = auth()->user();

        return $user instanceof Model ? app(CustomerPaymentMethodsQuery::class)->getForUser($user) : [];
    }

    public function setDefaultPaymentMethod(string $gateway, string $methodId): void
    {
        $user = auth()->user();

        if (! $user instanceof Model) {
            return;
        }

        try {
            app(ManageCustomerPaymentMethod::class)->setDefault($user, $gateway, $methodId);

            Notification::make()
                ->title(__('filament-cashier::portal.payment_methods.default_updated'))
                ->success()
                ->send();
        } catch (AuthorizationException) {
            $this->notifyFailure(true);
        } catch (Throwable) {
            $this->notifyFailure(false);
        }
    }

    public function deletePaymentMethod(string $gateway, string $methodId): void
    {
        $user = auth()->user();

        if (! $user instanceof Model) {
            return;
        }

        try {
            app(ManageCustomerPaymentMethod::class)->delete($user, $gateway, $methodId);

            Notification::make()
                ->title(__('filament-cashier::portal.payment_methods.deleted'))
                ->success()
                ->send();
        } catch (AuthorizationException) {
            $this->notifyFailure(true);
        } catch (Throwable) {
            $this->notifyFailure(false);
        }
    }

    private function notifyFailure(bool $unauthorized): void
    {
        $notification = Notification::make()
            ->title(__('filament-cashier::portal.payment_methods.error'))
            ->danger();

        if ($unauthorized) {
            $notification->body(__('filament-cashier::portal.payment_methods.unauthorized'));
        }

        $notification->send();
    }
}
