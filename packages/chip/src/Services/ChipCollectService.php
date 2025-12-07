<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services;

use AIArmada\Chip\Builders\PurchaseBuilder;
use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Data\ClientData;
use AIArmada\Chip\Data\ClientDetailsData;
use AIArmada\Chip\Data\CompanyStatementData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Services\Collect\AccountApi;
use AIArmada\Chip\Services\Collect\ClientsApi;
use AIArmada\Chip\Services\Collect\PurchasesApi;
use AIArmada\Chip\Services\Collect\WebhooksApi;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class ChipCollectService
{
    private PurchasesApi $purchases;

    private ClientsApi $clients;

    private AccountApi $account;

    private WebhooksApi $webhooks;

    private ?SubscriptionService $subscriptionService = null;

    public function __construct(
        private ChipCollectClient $client,
        ?CacheRepository $cache = null,
    ) {
        $this->purchases = new PurchasesApi($cache, $client);
        $this->clients = new ClientsApi($client);
        $this->account = new AccountApi($client);
        $this->webhooks = new WebhooksApi($client);
    }

    public function purchase(): PurchaseBuilder
    {
        return new PurchaseBuilder($this);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPurchase(array $data): PurchaseData
    {
        return $this->purchases->create($data);
    }

    public function getPurchase(string $purchaseId): PurchaseData
    {
        return $this->purchases->find($purchaseId);
    }

    public function cancelPurchase(string $purchaseId): PurchaseData
    {
        return $this->purchases->cancel($purchaseId);
    }

    public function refundPurchase(string $purchaseId, ?int $amount = null): PurchaseData
    {
        return $this->purchases->refund($purchaseId, $amount);
    }

    /**
     * Get available payment methods
     *
     * @param  array<string, mixed>  $filters  Optional filters to override defaults (brand_id, currency, etc.)
     * @return array<string, mixed>
     */
    public function getPaymentMethods(array $filters = []): array
    {
        // Set default brand_id and currency from config if not provided
        $filters['brand_id'] ??= $this->getBrandId();
        $filters['currency'] ??= config('chip.defaults.currency', 'MYR');

        return $this->purchases->paymentMethods($filters);
    }

    public function chargePurchase(string $purchaseId, string $recurringToken): PurchaseData
    {
        return $this->purchases->charge($purchaseId, $recurringToken);
    }

    public function capturePurchase(string $purchaseId, ?int $amount = null): PurchaseData
    {
        return $this->purchases->capture($purchaseId, $amount);
    }

    public function releasePurchase(string $purchaseId): PurchaseData
    {
        return $this->purchases->release($purchaseId);
    }

    public function markPurchaseAsPaid(string $purchaseId, ?int $paidOn = null): PurchaseData
    {
        return $this->purchases->markAsPaid($purchaseId, $paidOn);
    }

    public function resendInvoice(string $purchaseId): PurchaseData
    {
        return $this->purchases->resendInvoice($purchaseId);
    }

    public function deleteRecurringToken(string $purchaseId): void
    {
        $this->purchases->deleteRecurringToken($purchaseId);
    }

    public function getBrandId(): string
    {
        return $this->client->getBrandId();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createClient(array $data): ClientData
    {
        return $this->clients->create($data);
    }

    public function getClient(string $clientId): ClientData
    {
        return $this->clients->find($clientId);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listClients(array $filters = []): array
    {
        return $this->clients->list($filters);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateClient(string $clientId, array $data): ClientData
    {
        return $this->clients->update($clientId, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function partialUpdateClient(string $clientId, array $data): ClientData
    {
        return $this->clients->partialUpdate($clientId, $data);
    }

    public function deleteClient(string $clientId): void
    {
        $this->clients->delete($clientId);
    }

    /**
     * @return array<string, mixed>
     */
    public function listClientRecurringTokens(string $clientId): array
    {
        return $this->clients->recurringTokens($clientId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getClientRecurringToken(string $clientId, string $tokenId): array
    {
        return $this->clients->recurringToken($clientId, $tokenId);
    }

    public function deleteClientRecurringToken(string $clientId, string $tokenId): void
    {
        $this->clients->deleteRecurringToken($clientId, $tokenId);
    }

    /**
     * @param  array<int, \AIArmada\Chip\Data\ProductData>  $products
     * @param  array<string, mixed>  $options
     */
    public function createCheckoutPurchase(array $products, ClientDetailsData $clientDetails, array $options = []): PurchaseData
    {
        return $this->purchases->createCheckoutPurchase($products, $clientDetails, $options);
    }

    public function getPublicKey(): string
    {
        return $this->purchases->publicKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function getAccountBalance(): array
    {
        return $this->account->balance();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getAccountTurnover(array $filters = []): array
    {
        return $this->account->turnover($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, CompanyStatementData>|array{data: array<int, CompanyStatementData>, meta?: array<string, mixed>}
     */
    public function listCompanyStatements(array $filters = []): array
    {
        $response = $this->account->companyStatements($filters);

        if (isset($response['data']) && is_array($response['data'])) {
            $response['data'] = array_map(static fn (array $item) => CompanyStatementData::fromArray($item), $response['data']);

            return $response;
        }

        if (array_is_list($response)) {
            return array_map(static fn (array $item) => CompanyStatementData::fromArray($item), $response);
        }

        return [];
    }

    public function getCompanyStatement(string $statementId): CompanyStatementData
    {
        $response = $this->account->companyStatement($statementId);

        return CompanyStatementData::fromArray($response);
    }

    public function cancelCompanyStatement(string $statementId): CompanyStatementData
    {
        $response = $this->account->cancelCompanyStatement($statementId);

        return CompanyStatementData::fromArray($response);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createWebhook(array $data): array
    {
        return $this->webhooks->create($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function getWebhook(string $webhookId): array
    {
        return $this->webhooks->find($webhookId);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateWebhook(string $webhookId, array $data): array
    {
        return $this->webhooks->update($webhookId, $data);
    }

    public function deleteWebhook(string $webhookId): void
    {
        $this->webhooks->delete($webhookId);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listWebhooks(array $filters = []): array
    {
        return $this->webhooks->list($filters);
    }

    public function subscriptions(): SubscriptionService
    {
        return $this->subscriptionService ??= new SubscriptionService($this);
    }
}
