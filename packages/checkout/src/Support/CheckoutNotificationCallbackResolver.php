<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Enums\PaymentStatus;
use Illuminate\Support\Arr;

final readonly class CheckoutNotificationCallbackResolver
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolveCallbackType(array $payload): ?string
    {
        return match ($this->extractPaymentStatus($payload)) {
            PaymentStatus::Completed => 'success',
            PaymentStatus::Failed => 'failure',
            PaymentStatus::Cancelled => 'cancel',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function extractPaymentStatus(array $payload): PaymentStatus
    {
        $status = Arr::get($payload, 'status') ?? Arr::get($payload, 'data.object.status');

        return match ($status) {
            'paid', 'completed', 'succeeded', 'complete' => PaymentStatus::Completed,
            'pending', 'created', 'processing' => PaymentStatus::Pending,
            'failed', 'error', 'payment_failed' => PaymentStatus::Failed,
            'cancelled', 'canceled', 'expired' => PaymentStatus::Cancelled,
            'refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Processing,
        };
    }
}
