<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Models\CheckoutSession;

final readonly class ChipPurchasePayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(CheckoutSession $session, PaymentRequest $request): array
    {
        return [
            'purchase' => [
                'products' => [
                    [
                        'name' => $request->description ?? "Checkout {$session->id}",
                        'price' => $request->amount,
                        'quantity' => 1,
                    ],
                ],
                'currency' => $request->currency,
            ],
            'client' => [
                'email' => $request->customerEmail,
                'full_name' => $request->customerName,
                'phone' => $request->customerPhone,
            ],
            'reference' => $session->id,
            'success_redirect' => $request->successUrl,
            'failure_redirect' => $request->failureUrl,
            'cancel_redirect' => $request->cancelUrl,
        ];
    }
}
