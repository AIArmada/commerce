<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\CustomerPortal\Widgets;

use AIArmada\FilamentCashier\Support\GatewayDetector;
use Exception;
use Filament\Widgets\Widget;

final class PaymentMethodsPreviewWidget extends Widget
{
    protected string $view = 'filament-cashier::customer-portal.widgets.payment-methods-preview';

    protected int | string | array $columnSpan = 1;

    protected static ?int $sort = 2;

    /**
     * @return array<string, array{type: string, last4: string, is_default: bool}>
     */
    public function getPaymentMethods(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        $methods = [];
        $detector = app(GatewayDetector::class);

        // Get default Stripe payment method
        if ($detector->isAvailable('stripe') && method_exists($user, 'defaultPaymentMethod')) {
            try {
                $default = $user->defaultPaymentMethod();
                if ($default) {
                    $methods['stripe'] = [
                        'type' => $default->card->brand ?? 'Card',
                        'last4' => $default->card->last4 ?? '****',
                        'is_default' => true,
                    ];
                }
            } catch (Exception) {
                // Silently fail
            }
        }

        // Get default CHIP payment method
        if ($detector->isAvailable('chip') && method_exists($user, 'defaultChipPaymentMethod')) {
            try {
                $default = $user->defaultChipPaymentMethod();
                if ($default) {
                    $methods['chip'] = [
                        'type' => $default->type ?? 'Payment Method',
                        'last4' => $default->last4 ?? '****',
                        'is_default' => true,
                    ];
                }
            } catch (Exception) {
                // Silently fail
            }
        }

        return $methods;
    }
}
