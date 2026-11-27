<?php

declare(strict_types=1);

use AIArmada\Chip\Features\Collect\Actions\ClientsApi;
use AIArmada\Chip\Features\Collect\Actions\PurchasesApi;
use AIArmada\Chip\Features\Collect\Api\ClientOperations;
use AIArmada\Chip\Features\Collect\Api\PurchaseOperations;
use AIArmada\Chip\Features\Statements\Actions\AccountApi;
use AIArmada\Chip\Features\Statements\Api\AccountOperations;
use AIArmada\Chip\Features\WebhookDelivery\Actions\WebhooksApi;
use AIArmada\Chip\Features\WebhookDelivery\Api\WebhookRegistrationOperations;

it('registers Collect operations through the owning feature modules', function (string $contract, string $implementation): void {
    expect(app($contract))->toBeInstanceOf($implementation);
})->with([
    'purchases' => [PurchaseOperations::class, PurchasesApi::class],
    'clients' => [ClientOperations::class, ClientsApi::class],
    'accounts and statements' => [AccountOperations::class, AccountApi::class],
    'webhook registration' => [WebhookRegistrationOperations::class, WebhooksApi::class],
]);
