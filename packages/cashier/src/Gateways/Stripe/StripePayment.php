<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Stripe;

use AIArmada\Cashier\Contracts\PaymentContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Laravel\Cashier\Exceptions\PaymentActionRequired;
use Laravel\Cashier\Exceptions\PaymentFailure;
use Laravel\Cashier\Payment;
use Stripe\StripeClient;

/**
 * Wrapper for Stripe payment.
 */
class StripePayment implements PaymentContract
{
    protected Payment $payment;

    /**
     * Create a new Stripe payment wrapper.
     */
    public function __construct(mixed $payment)
    {
        if (! $payment instanceof Payment) {
            throw new InvalidArgumentException('StripePayment expects an instance of ' . Payment::class);
        }

        $this->payment = $payment;
    }

    /**
     * Get the payment ID.
     */
    public function id(): string
    {
        return (string) $this->payment->asStripePaymentIntent()->id;
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return 'stripe';
    }

    /**
     * Determine if the payment is pending.
     */
    public function isPending(): bool
    {
        return $this->payment->requiresAction() || $this->payment->requiresPaymentMethod();
    }

    /**
     * Determine if the payment succeeded.
     */
    public function isSucceeded(): bool
    {
        return $this->payment->isSucceeded();
    }

    /**
     * Determine if the payment failed.
     */
    public function isFailed(): bool
    {
        // Stripe PaymentIntents don't have an explicit "failed" status.
        // A terminal failure is typically represented as "requires_payment_method".
        return $this->status() === 'requires_payment_method';
    }

    /**
     * Determine if the payment is expired.
     */
    public function isExpired(): bool
    {
        return $this->payment->isCanceled() && $this->status() === 'expired';
    }

    /**
     * Determine if the payment was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->payment->isCanceled();
    }

    /**
     * Determine if the payment was canceled (alias for isCancelled).
     */
    public function isCanceled(): bool
    {
        return $this->isCancelled();
    }

    /**
     * Determine if the payment requires action (e.g., 3DS).
     */
    public function requiresAction(): bool
    {
        return $this->payment->requiresAction();
    }

    /**
     * Determine if the payment was refunded.
     */
    public function isRefunded(): bool
    {
        $paymentIntent = $this->payment->asStripePaymentIntent();

        $latestCharge = $paymentIntent->latest_charge;

        if (! is_string($latestCharge) || $latestCharge === '') {
            return false;
        }

        $secret = config('cashier.gateways.stripe.secret');

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $stripe = new StripeClient($secret);
        $charge = $stripe->charges->retrieve($latestCharge);

        return ($charge->amount_refunded ?? 0) > 0;
    }

    /**
     * Determine if the payment requires redirect.
     */
    public function requiresRedirect(): bool
    {
        return $this->payment->requiresAction();
    }

    /**
     * Get the redirect URL if required.
     */
    public function redirectUrl(): ?string
    {
        if (! $this->requiresRedirect()) {
            return null;
        }

        $paymentIntent = $this->payment->asStripePaymentIntent();

        /** @var string|null $url */
        $url = Arr::get($paymentIntent->toArray(), 'next_action.redirect_to_url.url');

        return $url;
    }

    /**
     * Redirect to complete the payment.
     */
    public function redirect(): RedirectResponse
    {
        return redirect()->to($this->redirectUrl());
    }

    /**
     * Get the receipt/invoice URL.
     */
    public function receiptUrl(): ?string
    {
        $paymentIntent = $this->payment->asStripePaymentIntent();
        $latestCharge = $paymentIntent->latest_charge;

        if ($latestCharge && is_string($latestCharge)) {
            $secret = config('cashier.gateways.stripe.secret');

            if (! is_string($secret) || $secret === '') {
                return null;
            }

            $stripe = new StripeClient($secret);
            $charge = $stripe->charges->retrieve($latestCharge);

            return $charge->receipt_url;
        }

        return null;
    }

    /**
     * Get the currency.
     */
    public function currency(): string
    {
        return mb_strtoupper((string) $this->payment->asStripePaymentIntent()->currency);
    }

    /**
     * Get the formatted amount.
     */
    public function amount(): string
    {
        return $this->payment->amount();
    }

    /**
     * Get the raw amount in cents.
     */
    public function rawAmount(): int
    {
        return (int) $this->payment->rawAmount();
    }

    /**
     * Get the payment status.
     */
    public function status(): string
    {
        return $this->payment->asStripePaymentIntent()->status;
    }

    /**
     * Get an optional gateway error code.
     */
    public function errorCode(): ?string
    {
        return null;
    }

    /**
     * Validate the payment and throw exception if failed.
     *
     * @throws PaymentActionRequired
     * @throws PaymentFailure
     */
    public function validate(): static
    {
        $this->payment->validate();

        return $this;
    }

    /**
     * Get the underlying payment.
     */
    public function asGatewayPayment(): Payment
    {
        return $this->payment;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'gateway' => $this->gateway(),
            'status' => $this->status(),
            'currency' => $this->currency(),
            'amount' => $this->amount(),
            'raw_amount' => $this->rawAmount(),
            'is_succeeded' => $this->isSucceeded(),
            'is_pending' => $this->isPending(),
            'is_failed' => $this->isFailed(),
            'requires_redirect' => $this->requiresRedirect(),
        ];
    }

    /**
     * Convert to JSON.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
