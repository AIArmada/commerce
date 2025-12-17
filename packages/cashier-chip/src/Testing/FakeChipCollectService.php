<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Testing;

use AIArmada\Chip\Builders\PurchaseBuilder;
use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Data\ClientData;
use AIArmada\Chip\Data\ClientDetailsData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Services\ChipCollectService;
use Mockery;

/**
 * Fake CHIP Collect Service for testing purposes.
 *
 * This class wraps FakeChipClient and provides the same interface
 * as the real ChipCollectService, allowing it to be used as a
 * drop-in replacement during tests.
 */
class FakeChipCollectService extends ChipCollectService
{
    /**
     * The fake CHIP client.
     */
    protected FakeChipClient $fakeClient;

    /**
     * Create a new fake service instance.
     */
    public function __construct(?FakeChipClient $fakeClient = null)
    {
        $this->fakeClient = $fakeClient ?? new FakeChipClient;

        // Pass a dummy client to the parent constructor to satisfy requirements
        // The parent methods won't be used since we override everything
        /** @var Mockery\MockInterface&ChipCollectClient $dummyClient */
        $dummyClient = Mockery::mock(ChipCollectClient::class);
        $dummyClient->shouldIgnoreMissing();

        parent::__construct($dummyClient);
    }

    public function purchase(): PurchaseBuilder
    {
        return new PurchaseBuilder($this);
    }

    /**
     * Get the underlying fake client.
     */
    public function getFakeClient(): FakeChipClient
    {
        return $this->fakeClient;
    }

    /**
     * Get the brand ID.
     */
    public function getBrandId(): string
    {
        return $this->fakeClient->getBrandId();
    }

    /**
     * Create a purchase.
     *
     * @param  array<string, mixed>  $data
     */
    public function createPurchase(array $data): PurchaseData
    {
        $response = $this->fakeClient->createPurchase($data);

        return PurchaseData::from($response);
    }

    /**
     * Get a purchase.
     */
    public function getPurchase(string $purchaseId): PurchaseData
    {
        $response = $this->fakeClient->getPurchase($purchaseId);

        return PurchaseData::from($response ?? []);
    }

    /**
     * Cancel a purchase.
     */
    public function cancelPurchase(string $purchaseId): PurchaseData
    {
        $response = $this->fakeClient->cancelPurchase($purchaseId);

        return PurchaseData::from($response ?? []);
    }

    /**
     * Refund a purchase.
     */
    public function refundPurchase(string $purchaseId, ?int $amount = null): PurchaseData
    {
        $response = $this->fakeClient->refundPurchase($purchaseId, $amount);

        return PurchaseData::from($response ?? []);
    }

    /**
     * Get available payment methods.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getPaymentMethods(array $filters = []): array
    {
        return $this->fakeClient->getPaymentMethods($filters);
    }

    /**
     * Charge a purchase with a recurring token.
     */
    public function chargePurchase(string $purchaseId, string $recurringToken): PurchaseData
    {
        $response = $this->fakeClient->chargePurchase($purchaseId, $recurringToken);

        return PurchaseData::from($response ?? []);
    }

    /**
     * Capture a purchase.
     */
    public function capturePurchase(string $purchaseId, ?int $amount = null): PurchaseData
    {
        $response = $this->fakeClient->capturePurchase($purchaseId, $amount);

        return PurchaseData::from($response ?? []);
    }

    /**
     * Release a purchase.
     */
    public function releasePurchase(string $purchaseId): PurchaseData
    {
        $response = $this->fakeClient->releasePurchase($purchaseId);

        return PurchaseData::from($response ?? []);
    }

    /**
     * Mark a purchase as paid.
     */
    public function markPurchaseAsPaid(string $purchaseId, ?int $paidOn = null): PurchaseData
    {
        $response = $this->fakeClient->markPurchaseAsPaid($purchaseId, $paidOn);

        return PurchaseData::from($response ?? []);
    }

    /**
     * Resend invoice.
     */
    public function resendInvoice(string $purchaseId): PurchaseData
    {
        // Just return the purchase as-is
        $response = $this->fakeClient->getPurchase($purchaseId);

        return PurchaseData::from($response ?? []);
    }

    /**
     * Delete a recurring token from a purchase.
     */
    public function deleteRecurringToken(string $purchaseId): void
    {
        $this->fakeClient->deleteRecurringToken($purchaseId);
    }

    /**
     * Create a client.
     *
     * @param  array<string, mixed>  $data
     */
    public function createClient(array $data): ClientData
    {
        $response = $this->fakeClient->createClient($data);

        return ClientData::from($response);
    }

    /**
     * Get a client.
     */
    public function getClient(string $clientId): ClientData
    {
        $response = $this->fakeClient->getClient($clientId);

        return ClientData::from($response ?? []);
    }

    /**
     * List clients.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listClients(array $filters = []): array
    {
        return $this->fakeClient->listClients($filters);
    }

    /**
     * Update a client.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateClient(string $clientId, array $data): ClientData
    {
        $response = $this->fakeClient->updateClient($clientId, $data);

        return ClientData::from($response ?? []);
    }

    /**
     * Partial update a client.
     *
     * @param  array<string, mixed>  $data
     */
    public function partialUpdateClient(string $clientId, array $data): ClientData
    {
        $response = $this->fakeClient->updateClient($clientId, $data);

        return ClientData::from($response ?? []);
    }

    /**
     * Delete a client.
     */
    public function deleteClient(string $clientId): void
    {
        $this->fakeClient->deleteClient($clientId);
    }

    /**
     * List recurring tokens for a client.
     */
    public function listClientRecurringTokens(string $clientId): array
    {
        return [
            'results' => $this->fakeClient->listClientRecurringTokens($clientId),
        ];
    }

    /**
     * Get a recurring token for a client.
     *
     * @return array<string, mixed>
     */
    public function getClientRecurringToken(string $clientId, string $tokenId): array
    {
        return $this->fakeClient->getClientRecurringToken($clientId, $tokenId) ?? [];
    }

    /**
     * Delete a recurring token from a client.
     */
    public function deleteClientRecurringToken(string $clientId, string $tokenId): void
    {
        $this->fakeClient->deleteClientRecurringToken($clientId, $tokenId);
    }

    /**
     * Create a checkout purchase.
     *
     * @param  array<int, \AIArmada\Chip\Data\ProductData>  $products
     * @param  array<string, mixed>  $options
     */
    public function createCheckoutPurchase(array $products, ClientDetailsData $clientDetails, array $options = []): PurchaseData
    {
        $data = array_merge([
            'client' => [
                'email' => $clientDetails->email,
                'phone' => $clientDetails->phone,
                'full_name' => $clientDetails->full_name,
            ],
            'purchase' => [
                'products' => array_map(fn ($p) => $p->toArray(), $products),
                'currency' => $options['currency'] ?? 'MYR',
                'total' => array_sum(array_map(fn ($p) => $p->getTotalPriceInCents(), $products)),
            ],
        ], $options);

        $response = $this->fakeClient->createPurchase($data);

        return PurchaseData::from($response);
    }

    /**
     * Get the public key.
     */
    public function getPublicKey(): string
    {
        return $this->fakeClient->getPublicKey();
    }

    /**
     * Get account balance.
     *
     * @return array<string, mixed>
     */
    public function getAccountBalance(): array
    {
        return $this->fakeClient->getAccountBalance();
    }

    /**
     * Get account turnover.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getAccountTurnover(array $filters = []): array
    {
        return $this->fakeClient->getAccountTurnover($filters);
    }

    /**
     * List company statements.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listCompanyStatements(array $filters = []): array
    {
        return [
            'data' => [],
            'meta' => ['total' => 0],
        ];
    }

    /**
     * Get a company statement.
     */
    public function getCompanyStatement(string $statementId): \AIArmada\Chip\Data\CompanyStatementData
    {
        return \AIArmada\Chip\Data\CompanyStatementData::from([
            'id' => $statementId,
            'url' => 'http://example.com/statement.pdf',
            'period_start' => time(),
            'period_end' => time(),
            'created_on' => time(),
            'status' => 'generated',
        ]);
    }

    /**
     * Cancel a company statement.
     */
    public function cancelCompanyStatement(string $statementId): \AIArmada\Chip\Data\CompanyStatementData
    {
        return \AIArmada\Chip\Data\CompanyStatementData::from([
            'id' => $statementId,
            'url' => 'http://example.com/statement.pdf',
            'period_start' => time(),
            'period_end' => time(),
            'created_on' => time(),
            'status' => 'cancelled',
        ]);
    }

    /**
     * Create a webhook.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createWebhook(array $data): array
    {
        return $this->fakeClient->createWebhook($data);
    }

    /**
     * Get a webhook.
     *
     * @return array<string, mixed>
     */
    public function getWebhook(string $webhookId): array
    {
        return $this->fakeClient->getWebhook($webhookId) ?? [];
    }

    /**
     * Update a webhook.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateWebhook(string $webhookId, array $data): array
    {
        return $this->fakeClient->updateWebhook($webhookId, $data) ?? [];
    }

    /**
     * Delete a webhook.
     */
    public function deleteWebhook(string $webhookId): void
    {
        $this->fakeClient->deleteWebhook($webhookId);
    }

    /**
     * List webhooks.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listWebhooks(array $filters = []): array
    {
        return $this->fakeClient->listWebhooks($filters);
    }

    /**
     * Reset all fake data.
     */
    public function reset(): void
    {
        $this->fakeClient->reset();
    }

    /**
     * Add a recurring token to a client.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    public function addRecurringToken(string $clientId, ?array $data = null): array
    {
        return $this->fakeClient->addRecurringToken($clientId, $data);
    }

    /**
     * Simulate a payment being completed.
     */
    public function simulatePaymentComplete(string $purchaseId, ?string $recurringToken = null): ?PurchaseData
    {
        $response = $this->fakeClient->simulatePaymentComplete($purchaseId, $recurringToken);

        return $response ? PurchaseData::from($response) : null;
    }

    /**
     * Simulate a payment failure.
     */
    public function simulatePaymentFailure(string $purchaseId, string $reason = 'Payment declined'): ?PurchaseData
    {
        $response = $this->fakeClient->simulatePaymentFailure($purchaseId, $reason);

        return $response ? PurchaseData::from($response) : null;
    }
}
