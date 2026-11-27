<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect;

use AIArmada\Chip\Features\Collect\Actions\ApplyPurchaseWebhookState;
use AIArmada\Chip\Features\Collect\Actions\ClientsApi;
use AIArmada\Chip\Features\Collect\Actions\PurchaseInvoiceActions;
use AIArmada\Chip\Features\Collect\Actions\PurchasesApi;
use AIArmada\Chip\Features\Collect\Actions\StoreChipWebhookPayload;
use AIArmada\Chip\Features\Collect\Actions\SyncPurchaseRefundState;
use AIArmada\Chip\Features\Collect\Api\ClientOperations;
use AIArmada\Chip\Features\Collect\Api\PurchaseAnalytics;
use AIArmada\Chip\Features\Collect\Api\PurchaseInvoiceOperations;
use AIArmada\Chip\Features\Collect\Api\PurchaseMirror;
use AIArmada\Chip\Features\Collect\Api\PurchaseOperations;
use AIArmada\Chip\Features\Collect\Api\PurchaseWebhookState;
use AIArmada\Chip\Features\Collect\Api\RefundStateSynchronizer;
use AIArmada\Chip\Features\Collect\Queries\LocalAnalyticsService;
use AIArmada\Chip\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(PurchaseOperations::class, PurchasesApi::class);
        $app->bind(ClientOperations::class, ClientsApi::class);
        $app->bind(PurchaseMirror::class, StoreChipWebhookPayload::class);
        $app->bind(RefundStateSynchronizer::class, SyncPurchaseRefundState::class);
        $app->bind(PurchaseWebhookState::class, ApplyPurchaseWebhookState::class);
        $app->bind(PurchaseAnalytics::class, LocalAnalyticsService::class);
        $app->bind(PurchaseInvoiceOperations::class, PurchaseInvoiceActions::class);
    }

    public function boot(Application $app): void {}
}
