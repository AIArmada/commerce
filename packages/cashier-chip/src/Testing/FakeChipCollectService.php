<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Testing;

use AIArmada\Chip\Builders\PurchaseBuilder;
use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Data\ClientData;
use AIArmada\Chip\Data\ClientDetailsData;
use AIArmada\Chip\Data\CompanyStatementData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Services\ChipCollectService;
use Mockery;

/**
 * Fake CHIP Collect Service for testing as a ChipCollectService drop-in.
 *
 * CANONICAL test double for code depending on ChipCollectService.
 * Wraps FakeChipClient (the canonical data store) and provides typed
 * DTO responses matching the real service interface.
 *
 * @see FakeChipClient for low-level CHIP API mock responses.
 */
class FakeChipCollectService extends ChipCollectService
{
    protected FakeChipClient $fakeClient;

    public function __construct(?FakeChipClient $fakeClient = null)
    {
        $this->fakeClient = $fakeClient ?? new FakeChipClient;

        /** @var Mockery\MockInterface&ChipCollectClient $dummyClient */
        $dummyClient = Mockery::mock(ChipCollectClient::class);
        $dummyClient->shouldIgnoreMissing();

        parent::__construct($dummyClient);
    }

    public function purchase(): PurchaseBuilder
    {
        return new PurchaseBuilder($this);
    }

    public function getFakeClient(): FakeChipClient
    {
        return $this->fakeClient;
    }

    public function getBrandId(): string
    {
        return $this->fakeClient->getBrandId();
    }

    public function createPurchase(array $data): PurchaseData
    {
        $response = $this->fakeClient->createPurchase($data);

        return PurchaseData::from($response);
    }

    public function getPurchase(string $purchaseId): PurchaseData
    {
        $response = $this->fakeClient->getPurchase($purchaseId);

        return PurchaseData::from($response ?? []);
    }

    public function cancelPurchase(string $purchaseId): PurchaseData
    {
        $response = $this->fakeClient->cancelPurchase($purchaseId);

        return PurchaseData::from($response ?? []);
    }

    public function refundPurchase(string $purchaseId, ?int $amount = null): PurchaseData
    {
        $response = $this->fakeClient->refundPurchase($purchaseId, $amount);

        return PurchaseData::from($response ?? []);
    }

    public function getPaymentMethods(array $filters = []): array
    {
        return $this->fakeClient->getPaymentMethods($filters);
    }

    public function chargePurchase(string $purchaseId, string $recurringToken): PurchaseData
    {
        $response = $this->fakeClient->chargePurchase($purchaseId, $recurringToken);

        return PurchaseData::from($response ?? []);
    }

    public function capturePurchase(string $purchaseId, ?int $amount = null): PurchaseData
    {
        $response = $this->fakeClient->capturePurchase($purchaseId, $amount);

        return PurchaseData::from($response ?? []);
    }

    public function releasePurchase(string $purchaseId): PurchaseData
    {
        $response = $this->fakeClient->releasePurchase($purchaseId);

        return PurchaseData::from($response ?? []);
    }

    public function markPurchaseAsPaid(string $purchaseId, ?int $paidOn = null): PurchaseData
    {
        $response = $this->fakeClient->markPurchaseAsPaid($purchaseId, $paidOn);

        return PurchaseData::from($response ?? []);
    }

    public function resendInvoice(string $purchaseId): PurchaseData
    {
        $response = $this->fakeClient->getPurchase($purchaseId);

        return PurchaseData::from($response ?? []);
    }

    public function deleteRecurringToken(string $purchaseId): PurchaseData
    {
        $this->fakeClient->deleteRecurringToken($purchaseId);

        $response = $this->fakeClient->getPurchase($purchaseId);

        return PurchaseData::from($response ?? []);
    }

    public function createClient(array $data): ClientData
    {
        $response = $this->fakeClient->createClient($data);

        return ClientData::from($response);
    }

    public function getClient(string $clientId): ClientData
    {
        $response = $this->fakeClient->getClient($clientId);

        return ClientData::from($response ?? []);
    }

    public function listClients(array $filters = []): array
    {
        return $this->fakeClient->listClients($filters);
    }

    public function updateClient(string $clientId, array $data): ClientData
    {
        $response = $this->fakeClient->updateClient($clientId, $data);

        return ClientData::from($response ?? []);
    }

    public function partialUpdateClient(string $clientId, array $data): ClientData
    {
        $response = $this->fakeClient->updateClient($clientId, $data);

        return ClientData::from($response ?? []);
    }

    public function deleteClient(string $clientId): void
    {
        $this->fakeClient->deleteClient($clientId);
    }

    public function listClientRecurringTokens(string $clientId): array
    {
        return [
            'results' => $this->fakeClient->listClientRecurringTokens($clientId),
        ];
    }

    public function getClientRecurringToken(string $clientId, string $tokenId): array
    {
        return $this->fakeClient->getClientRecurringToken($clientId, $tokenId) ?? [];
    }

    public function deleteClientRecurringToken(string $clientId, string $tokenId): void
    {
        $this->fakeClient->deleteClientRecurringToken($clientId, $tokenId);
    }

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

    public function getPublicKey(): string
    {
        return $this->fakeClient->getPublicKey();
    }

    public function getAccountBalance(): array
    {
        return $this->fakeClient->getAccountBalance();
    }

    public function getAccountTurnover(array $filters = []): array
    {
        return $this->fakeClient->getAccountTurnover($filters);
    }

    public function listCompanyStatements(array $filters = []): array
    {
        return [
            'data' => [],
            'meta' => ['total' => 0],
        ];
    }

    public function getCompanyStatement(string $statementId): CompanyStatementData
    {
        return CompanyStatementData::from([
            'id' => $statementId,
            'url' => 'http://example.com/statement.pdf',
            'period_start' => time(),
            'period_end' => time(),
            'created_on' => time(),
            'status' => 'generated',
        ]);
    }

    public function cancelCompanyStatement(string $statementId): CompanyStatementData
    {
        return CompanyStatementData::from([
            'id' => $statementId,
            'url' => 'http://example.com/statement.pdf',
            'period_start' => time(),
            'period_end' => time(),
            'created_on' => time(),
            'status' => 'cancelled',
        ]);
    }

    public function createWebhook(array $data): array
    {
        return $this->fakeClient->createWebhook($data);
    }

    public function getWebhook(string $webhookId): array
    {
        return $this->fakeClient->getWebhook($webhookId) ?? [];
    }

    public function updateWebhook(string $webhookId, array $data): array
    {
        return $this->fakeClient->updateWebhook($webhookId, $data) ?? [];
    }

    public function deleteWebhook(string $webhookId): void
    {
        $this->fakeClient->deleteWebhook($webhookId);
    }

    public function listWebhooks(array $filters = []): array
    {
        return $this->fakeClient->listWebhooks($filters);
    }

    public function reset(): void
    {
        $this->fakeClient->reset();
    }

    public function addRecurringToken(string $clientId, ?array $data = null): array
    {
        return $this->fakeClient->addRecurringToken($clientId, $data);
    }

    public function simulatePaymentComplete(string $purchaseId, ?string $recurringToken = null): ?PurchaseData
    {
        $response = $this->fakeClient->simulatePaymentComplete($purchaseId, $recurringToken);

        return $response ? PurchaseData::from($response) : null;
    }

    public function simulatePaymentFailure(string $purchaseId, string $reason = 'Payment declined'): ?PurchaseData
    {
        $response = $this->fakeClient->simulatePaymentFailure($purchaseId, $reason);

        return $response ? PurchaseData::from($response) : null;
    }
}
