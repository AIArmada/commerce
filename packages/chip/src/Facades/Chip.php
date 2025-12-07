<?php

declare(strict_types=1);

namespace AIArmada\Chip\Facades;

use AIArmada\Chip\Services\ChipCollectService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \AIArmada\Chip\Builders\PurchaseBuilder purchase()
 * @method static \AIArmada\Chip\Data\Purchase createPurchase(array<string, mixed> $data)
 * @method static \AIArmada\Chip\Data\Purchase getPurchase(string $id)
 * @method static \AIArmada\Chip\Data\Purchase cancelPurchase(string $id)
 * @method static \AIArmada\Chip\Data\Purchase refundPurchase(string $id, int $amount = null)
 * @method static \AIArmada\Chip\Data\Purchase capturePurchase(string $id, int $amount = null)
 * @method static \AIArmada\Chip\Data\Purchase releasePurchase(string $id)
 * @method static \AIArmada\Chip\Data\Purchase chargePurchase(string $id, string $recurringToken)
 * @method static \AIArmada\Chip\Data\Purchase markPurchaseAsPaid(string $id, int $paidOn = null)
 * @method static \AIArmada\Chip\Data\Purchase resendInvoice(string $id)
 * @method static void deleteRecurringToken(string $id)
 * @method static array<string, mixed> getPaymentMethods(array<string, mixed> $filters = [])
 * @method static \AIArmada\Chip\Data\Purchase createCheckoutPurchase(array<int, \AIArmada\Chip\Data\Product> $products, \AIArmada\Chip\Data\ClientDetails $clientDetails, array<string, mixed> $options = [])
 * @method static string getBrandId()
 * @method static \AIArmada\Chip\Data\Client createClient(array<string, mixed> $data)
 * @method static \AIArmada\Chip\Data\Client getClient(string $id)
 * @method static \AIArmada\Chip\Data\Client updateClient(string $id, array<string, mixed> $data)
 * @method static \AIArmada\Chip\Data\Client partialUpdateClient(string $id, array<string, mixed> $data)
 * @method static void deleteClient(string $id)
 * @method static array<string, mixed> listClients(array<string, mixed> $filters = [])
 * @method static array<string, mixed> listClientRecurringTokens(string $clientId)
 * @method static array<string, mixed> getClientRecurringToken(string $clientId, string $tokenId)
 * @method static void deleteClientRecurringToken(string $clientId, string $tokenId)
 * @method static string getPublicKey()
 * @method static array<string, mixed> getAccountBalance()
 * @method static array<string, mixed> getAccountTurnover(array<string, mixed> $filters = [])
 * @method static array<int, \AIArmada\Chip\Data\CompanyStatement>|array{data: array<int, \AIArmada\Chip\Data\CompanyStatement>, meta?: array<string, mixed>} listCompanyStatements(array<string, mixed> $filters = [])
 * @method static \AIArmada\Chip\Data\CompanyStatement getCompanyStatement(string $statementId)
 * @method static \AIArmada\Chip\Data\CompanyStatement cancelCompanyStatement(string $statementId)
 * @method static array<string, mixed> createWebhook(array<string, mixed> $data)
 * @method static array<string, mixed> getWebhook(string $id)
 * @method static array<string, mixed> updateWebhook(string $id, array<string, mixed> $data)
 * @method static void deleteWebhook(string $id)
 * @method static array<string, mixed> listWebhooks(array<string, mixed> $filters = [])
 * @method static \AIArmada\Chip\Services\SubscriptionService subscriptions()
 *
 * @see ChipCollectService
 */
final class Chip extends Facade
{
    /**
     * Get the absolute URL for the CHIP webhook endpoint.
     */
    public static function webhookUrl(): string
    {
        $route = config('chip.webhooks.route', '/chip/webhook');

        return url($route);
    }

    protected static function getFacadeAccessor(): string
    {
        return ChipCollectService::class;
    }
}
