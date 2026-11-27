<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect\Api;

use AIArmada\Chip\Data\ClientDetailsData;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\ProductData;
use AIArmada\Chip\Data\PurchaseData;

interface PurchaseOperations
{
    /** @param array<string, mixed> $data */
    public function create(array $data, ?string $idempotencyKey = null): PurchaseData;

    public function find(string $purchaseId): PurchaseData;

    public function cancel(string $purchaseId, ?string $idempotencyKey = null): PurchaseData;

    public function refund(string $purchaseId, ?int $amount = null, ?string $idempotencyKey = null): PurchaseData | PaymentData;

    public function charge(string $purchaseId, string $recurringToken, ?string $idempotencyKey = null): PurchaseData;

    public function capture(string $purchaseId, ?int $amount = null, ?string $idempotencyKey = null): PurchaseData;

    public function release(string $purchaseId, ?string $idempotencyKey = null): PurchaseData;

    public function markAsPaid(string $purchaseId, ?int $paidOn = null, ?string $idempotencyKey = null): PurchaseData;

    public function resendInvoice(string $purchaseId, ?string $idempotencyKey = null): PurchaseData;

    public function deleteRecurringToken(string $purchaseId, ?string $idempotencyKey = null): PurchaseData;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function paymentMethods(array $filters = []): array;

    /**
     * @param  array<int, ProductData>  $products
     * @param  array<string, mixed>  $options
     */
    public function createCheckoutPurchase(array $products, ClientDetailsData $client, array $options = []): PurchaseData;

    public function publicKey(): string;
}
