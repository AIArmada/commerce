<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventOrderData;
use AIArmada\Events\Data\EventOrderItemData;
use AIArmada\Events\Data\EventRefundMatchData;
use AIArmada\Events\Data\EventRefundResultData;
use AIArmada\Events\Data\EventRefundStateData;

interface EventOrderDriver
{
    public function isPaidOrder(string $orderId): bool;

    public function orderNumber(string $orderId): ?string;

    public function orderSnapshot(string $orderId): ?EventOrderData;

    public function orderItemSnapshot(string $orderItemId): ?EventOrderItemData;

    public function assertOrderVisible(string $orderId): void;

    public function refundState(string $refundId): ?EventRefundStateData;

    /**
     * @return array<string, mixed>|null Original item options when the marker was applied, null when already marked or missing.
     */
    public function markItemFulfillment(string $orderItemId): ?array;

    /**
     * @param  array<string, mixed>|null  $options
     */
    public function restoreItemOptions(string $orderItemId, ?array $options): void;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createRefund(
        string $orderId,
        int $amount,
        string $reason,
        ?string $transactionId = null,
        array $metadata = [],
    ): string;

    public function refundIdForRegistration(string $orderId, string $registrationId): ?string;

    public function claimRefund(string $refundId): bool;

    public function completeRefund(string $refundId, ?string $transactionId = null): void;

    public function failRefund(string $refundId, string $reason): void;

    public function submitRefund(string $refundId): EventRefundResultData;

    public function matchProviderRefundConfirmation(
        string $provider,
        ?string $providerRefundId,
        ?string $relatedPaymentId,
        int $amount,
        string $currency,
        ?string $eventRegistrationId,
    ): EventRefundMatchData;

    public function correlateRefundProviderReference(
        string $refundId,
        ?string $providerRefundId,
        ?string $relatedPaymentId,
    ): bool;
}
