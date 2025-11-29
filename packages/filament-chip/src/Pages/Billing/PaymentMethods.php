<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Pages\Billing;

use AIArmada\FilamentChip\Concerns\InteractsWithBillable;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class PaymentMethods extends Page
{
    use InteractsWithBillable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament-chip::pages.billing.payment-methods';

    public static function getNavigationLabel(): string
    {
        return __('Payment Methods');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-chip.billing.features.payment_methods', true);
    }

<<<<<<< Updated upstream
<<<<<<< Updated upstream
    public function getTitle(): string|Htmlable
    {
        return __('Payment Methods');
    }

=======
    /**
     * @return array<string, mixed>
     */
>>>>>>> Stashed changes
=======
    /**
     * @return array<string, mixed>
     */
>>>>>>> Stashed changes
    public function getViewData(): array
    {
        return [
            'billable' => $this->getBillable(),
            'paymentMethods' => $this->getPaymentMethods(),
            'defaultPaymentMethod' => $this->getDefaultPaymentMethod(),
        ];
    }

<<<<<<< Updated upstream
<<<<<<< Updated upstream
=======
    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_payment_method')
                ->label(__('Add Payment Method'))
                ->icon(Heroicon::OutlinedPlus)
                ->color('primary')
                ->url(fn () => $this->getAddPaymentMethodUrl())
                ->openUrlInNewTab(false),
        ];
    }

    public function getAddPaymentMethodUrl(): string
    {
        $billable = $this->getBillable();

        if (! $billable || ! method_exists($billable, 'setupPaymentMethodUrl')) {
            return '#';
        }

        $panelId = $this->getBillingPanelId();
        $successUrl = config('filament-chip.billing.redirects.after_payment_method_added')
            ?? route("filament.{$panelId}.pages.payment-methods");

        return $billable->setupPaymentMethodUrl([
            'success_url' => $successUrl,
            'cancel_url' => route("filament.{$panelId}.pages.payment-methods"),
        ]);
    }

>>>>>>> Stashed changes
    public function setAsDefault(string $paymentMethodId): void
    {
        $billable = $this->getBillable();

        if (! $billable) {
            Notification::make()
                ->title(__('Unable to update payment method'))
                ->danger()
                ->send();

            return;
        }

        try {
            $billable->updateDefaultPaymentMethod($paymentMethodId);

            Notification::make()
                ->title(__('Default payment method updated'))
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title(__('Failed to update default payment method'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function deletePaymentMethod(string $paymentMethodId): void
    {
        $billable = $this->getBillable();

        if (! $billable) {
            Notification::make()
                ->title(__('Unable to delete payment method'))
                ->danger()
                ->send();

            return;
        }

        try {
            $billable->deletePaymentMethod($paymentMethodId);

            Notification::make()
                ->title(__('Payment method deleted'))
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title(__('Failed to delete payment method'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function formatCardBrand(string $brand): string
    {
        $brands = [
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'amex' => 'American Express',
            'discover' => 'Discover',
            'jcb' => 'JCB',
            'diners' => 'Diners Club',
            'unionpay' => 'UnionPay',
        ];

        return $brands[mb_strtolower($brand)] ?? ucfirst($brand);
    }

    protected function getBillable(): mixed
    {
        $user = filament()->auth()->user();

        if (! $user) {
            return null;
        }

        $billableModel = config('filament-chip.billing.billable_model');

        if ($billableModel && $user instanceof $billableModel) {
            return $user;
        }

        if (method_exists($user, 'currentTeam')) {
            return $user->currentTeam;
        }

        return $user;
    }

=======
>>>>>>> Stashed changes
    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_payment_method')
                ->label(__('Add Payment Method'))
                ->icon(Heroicon::OutlinedPlus)
                ->color('primary')
                ->url(fn () => $this->getAddPaymentMethodUrl())
                ->openUrlInNewTab(false),
        ];
    }

    public function getAddPaymentMethodUrl(): string
    {
        $billable = $this->getBillable();

        if (! $billable || ! method_exists($billable, 'setupPaymentMethodUrl')) {
            return '#';
        }

        $panelId = $this->getBillingPanelId();
        $successUrl = config('filament-chip.billing.redirects.after_payment_method_added')
            ?? route("filament.{$panelId}.pages.payment-methods");

        return $billable->setupPaymentMethodUrl([
            'success_url' => $successUrl,
            'cancel_url' => route("filament.{$panelId}.pages.payment-methods"),
        ]);
    }
}
