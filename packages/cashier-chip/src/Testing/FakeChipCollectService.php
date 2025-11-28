<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Testing;

use AIArmada\Chip\DataObjects\Client;
use AIArmada\Chip\DataObjects\ClientDetails;
use AIArmada\Chip\DataObjects\Purchase;

/**
 * Fake CHIP Collect Service for testing purposes.
 *
 * This class wraps FakeChipClient and provides the same interface
 * as the real ChipCollectService, allowing it to be used as a
 * drop-in replacement during tests.
 */
class FakeChipCollectService
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
        $this->fakeClient = $fakeClient ?? new FakeChipClient();
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
    public function createPurchase(array $data): Purchase
    {
        $response = $this->fakeClient->createPurchase($data);

        return Purchase::fromArray($response);
    }

    /**
     * Get a purchase.
     */
    public function getPurchase(string $purchaseId): Purchase
    {
        $response = $this->fakeClient->getPurchase($purchaseId);

        return Purchase::fromArray($response ?? []);
    }

    /**
     * Cancel a purchase.
     */
    public function cancelPurchase(string $purchaseId): Purchase
    {
        $response = $this->fakeClient->cancelPurchase($purchaseId);

        return Purchase::fromArray($response ?? []);
    }

    /**
     * Refund a purchase.
     */
    public function refundPurchase(string $purchaseId, ?int $amount = null): Purchase
    {
        $response = $this->fakeClient->refundPurchase($purchaseId, $amount);

        return Purchase::fromArray($response ?? []);
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
    public function chargePurchase(string $purchaseId, string $recurringToken): Purchase
    {
        $response = $this->fakeClient->chargePurchase($purchaseId, $recurringToken);

        return Purchase::fromArray($response ?? []);
    }

    /**
     * Capture a purchase.
     */
    public function capturePurchase(string $purchaseId, ?int $amount = null): Purchase
    {
        $response = $this->fakeClient->capturePurchase($purchaseId, $amount);

        return Purchase::fromArray($response ?? []);
    }

    /**
     * Release a purchase.
     */
    public function releasePurchase(string $purchaseId): Purchase
    {
        $response = $this->fakeClient->releasePurchase($purchaseId);

        return Purchase::fromArray($response ?? []);
    }

    /**
     * Mark a purchase as paid.
     */
    public function markPurchaseAsPaid(string $purchaseId, ?int $paidOn = null): Purchase
    {
        $response = $this->fakeClient->markPurchaseAsPaid($purchaseId, $paidOn);

        return Purchase::fromArray($response ?? []);
    }

    /**
     * Resend invoice.
     */
    public function resendInvoice(string $purchaseId): Purchase
    {
        // Just return the purchase as-is
        $response = $this->fakeClient->getPurchase($purchaseId);

        return Purchase::fromArray($response ?? []);
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
    public function createClient(array $data): Client
    {
        $response = $this->fakeClient->createClient($data);

        return Client::fromArray($response);
    }

    /**
     * Get a client.
     */
    public function getClient(string $clientId): Client
    {
        $response = $this->fakeClient->getClient($clientId);

        return Client::fromArray($response ?? []);
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
    public function updateClient(string $clientId, array $data): Client
    {
        $response = $this->fakeClient->updateClient($clientId, $data);

        return Client::fromArray($response ?? []);
    }

    /**
     * Partial update a client.
     *
     * @param  array<string, mixed>  $data
     */
    public function partialUpdateClient(string $clientId, array $data): Client
    {
        $response = $this->fakeClient->updateClient($clientId, $data);

        return Client::fromArray($response ?? []);
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
     *
     * @return array<int, array<string, mixed>>
     */
    public function listClientRecurringTokens(string $clientId): array
    {
        return $this->fakeClient->listClientRecurringTokens($clientId);
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
     * @param  array<int, \AIArmada\Chip\DataObjects\Product>  $products
     * @param  array<string, mixed>  $options
     */
    public function createCheckoutPurchase(array $products, ClientDetails $clientDetails, array $options = []): Purchase
    {
        $data = array_merge([
            'client' => [
                'email' => $clientDetails->email,
                'phone' => $clientDetails->phone,
                'full_name' => $clientDetails->fullName,
            ],
            'purchase' => [
                'products' => array_map(fn ($p) => $p->toArray(), $products),
                'currency' => $options['currency'] ?? 'MYR',
                'total' => array_sum(array_map(fn ($p) => $p->price * $p->quantity, $products)),
            ],
        ], $options);

        $response = $this->fakeClient->createPurchase($data);

        return Purchase::fromArray($response);
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
     *
     * @return \AIArmada\Chip\DataObjects\CompanyStatement
     */
    public function getCompanyStatement(string $statementId): mixed
    {
        return null;
    }

    /**
     * Cancel a company statement.
     *
     * @return \AIArmada\Chip\DataObjects\CompanyStatement
     */
    public function cancelCompanyStatement(string $statementId): mixed
    {
        return null;
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
    public function simulatePaymentComplete(string $purchaseId, ?string $recurringToken = null): ?Purchase
    {
        $response = $this->fakeClient->simulatePaymentComplete($purchaseId, $recurringToken);

        return $response ? Purchase::fromArray($response) : null;
    }

    /**
     * Simulate a payment failure.
     */
    public function simulatePaymentFailure(string $purchaseId, string $reason = 'Payment declined'): ?Purchase
    {
        $response = $this->fakeClient->simulatePaymentFailure($purchaseId, $reason);

        return $response ? Purchase::fromArray($response) : null;
    }
}
