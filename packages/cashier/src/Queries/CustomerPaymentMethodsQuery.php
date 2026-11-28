<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Queries;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Support\ActionGuard;
use AIArmada\Cashier\Support\GatewayDetector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

final class CustomerPaymentMethodsQuery
{
    /** @return array<string, Collection<int, mixed>> */
    public function getForUser(Model $user): array
    {
        if (! $user->exists) {
            return [];
        }

        if ($user instanceof BillableContract) {
            ActionGuard::assertBillableInScope($user);
        }

        $methods = [];
        $detector = app(GatewayDetector::class);

        // Get Stripe payment methods
        if ($detector->isAvailable('stripe') && method_exists($user, 'paymentMethods')) {
            try {
                $stripeMethods = $user->paymentMethods();
                $defaultMethod = method_exists($user, 'defaultPaymentMethod') ? $user->defaultPaymentMethod() : null;
                if ($stripeMethods->isNotEmpty()) {
                    $methods['stripe'] = $stripeMethods->map(fn ($pm) => [
                        'id' => $pm->id,
                        'type' => $pm->card->brand ?? 'Card',
                        'last4' => $pm->card->last4 ?? '****',
                        'expiry' => ($pm->card->exp_month ?? '??') . '/' . ($pm->card->exp_year ?? '??'),
                        'is_default' => $defaultMethod !== null && $pm->id === $defaultMethod->id,
                    ]);
                }
            } catch (Throwable) {
                // Silently fail if API is not configured
            }
        }

        // Get CHIP payment methods
        if ($detector->isAvailable('chip') && method_exists($user, 'chipPaymentMethods')) {
            try {
                $chipMethods = $user->chipPaymentMethods();
                if ($chipMethods->isNotEmpty()) {
                    $methods['chip'] = $chipMethods->map(fn ($pm) => [
                        'id' => $pm->id,
                        'type' => $pm->type ?? 'Payment Method',
                        'last4' => $pm->last4 ?? '****',
                        'expiry' => $pm->expiry ?? 'N/A',
                        'is_default' => $pm->is_default ?? false,
                    ]);
                }
            } catch (Throwable) {
                // Silently fail if API is not configured
            }
        }

        return $methods;
    }
}
