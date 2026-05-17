<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class DemoPaymentController extends Controller
{
    public function show(CheckoutSession $checkoutSession): View
    {
        $this->ensureCheckoutSessionAccessible($checkoutSession);

        return view('payments.demo-gateway', [
            'checkoutSession' => $checkoutSession,
            'requestedPaymentMethod' => $checkoutSession->payment_data['requested_payment_method'] ?? 'card',
        ]);
    }

    public function process(CheckoutSession $checkoutSession, string $decision): RedirectResponse
    {
        $this->ensureCheckoutSessionAccessible($checkoutSession);

        abort_unless(in_array($decision, ['success', 'failure', 'cancel'], true), 404);

        $transactionId = $decision === 'success'
            ? 'demo-txn-'.Str::upper(Str::random(12))
            : null;

        $message = match ($decision) {
            'success' => 'Demo payment completed successfully.',
            'failure' => 'Demo payment failed.',
            default => 'Demo payment was cancelled.',
        };

        $checkoutSession->update([
            'payment_data' => array_merge($checkoutSession->payment_data ?? [], [
                'demo_gateway' => [
                    'status' => match ($decision) {
                        'success' => 'completed',
                        'failure' => 'failed',
                        default => 'cancelled',
                    },
                    'payment_id' => $checkoutSession->payment_id,
                    'transaction_id' => $transactionId,
                    'amount' => $checkoutSession->grand_total,
                    'currency' => $checkoutSession->currency,
                    'requested_payment_method' => $checkoutSession->payment_data['requested_payment_method'] ?? null,
                    'processed_at' => now()->toIso8601String(),
                    'message' => $message,
                ],
            ]),
        ]);

        $callbackToken = $checkoutSession->payment_data['callback_token'] ?? null;

        abort_unless(is_string($callbackToken) && $callbackToken !== '', 404);

        $routeName = match ($decision) {
            'success' => 'checkout.payment.success',
            'failure' => 'checkout.payment.failure',
            default => 'checkout.payment.cancel',
        };

        return redirect()->route($routeName, [
            'session' => $checkoutSession->id,
            'checkout_callback_token' => $callbackToken,
        ]);
    }

    private function ensureCheckoutSessionAccessible(CheckoutSession $checkoutSession): void
    {
        $owner = OwnerContext::resolve();

        if ($owner === null) {
            abort(404);
        }

        if ($checkoutSession->owner_type === null || $checkoutSession->owner_id === null) {
            abort(404);
        }

        if (
            $checkoutSession->owner_type !== $owner->getMorphClass()
            || (string) $checkoutSession->owner_id !== (string) $owner->getKey()
        ) {
            abort(404);
        }
    }
}
