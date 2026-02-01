<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Actions;

use AIArmada\Checkout\Models\CheckoutSession;
use Akaunting\Money\Money;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

final class BuildCheckoutSessionViewData
{
    use AsAction;

    /**
     * @return array<string, mixed>
     */
    public function handle(CheckoutSession $session): array
    {
        $order = $session->order;
        $currency = $session->currency ?? config('checkout.defaults.currency', 'MYR');
        $total = (int) ($session->grand_total ?? 0);
        $formattedTotal = Money::{$currency}($total)->format();
        $paymentData = is_array($session->payment_data) ? $session->payment_data : [];
        $reference = Arr::get($paymentData, 'reference') ?? $session->payment_id ?? $session->cart_id ?? $session->id;

        return [
            'session' => $session,
            'order' => $order,
            'reference' => $reference,
            'formattedTotal' => $formattedTotal,
            'shippingData' => $session->shipping_data ?? null,
        ];
    }
}
