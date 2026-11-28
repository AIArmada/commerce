<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

interface OrderRefunds
{
    /** @param array<string, mixed> $metadata */
    public function processRefund(string $orderId, int $amount, string $transactionId, string $reason, array $metadata = [], ?string $paymentId = null): void;

    /**
     * @param  array<string, mixed>  $metadata
     * @return list<string>
     */
    public function createPendingRefund(string $orderId, int $amount, string $transactionId, string $reason, array $metadata = [], ?string $paymentId = null): array;

    public function claimPendingRefundSubmission(string $refundId): bool;

    public function correlateProviderReference(string $refundId, ?string $providerRefundId, ?string $relatedPaymentId = null): bool;

    public function completePendingRefund(string $refundId, ?string $transactionId = null): void;

    public function failPendingRefund(string $refundId, string $reason): void;
}
