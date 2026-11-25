<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Webhooks;

use AIArmada\Affiliates\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Affiliates\Contracts\AffiliateWebhookTransport;
use AIArmada\Affiliates\Drivers\Webhooks\PinnedAffiliateWebhookTransport;
use AIArmada\Affiliates\Features\Webhooks\Api\WebhookApi;
use AIArmada\Affiliates\Features\Webhooks\Actions\EnqueueWebhook;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bindIf(AffiliateWebhookTransport::class, PinnedAffiliateWebhookTransport::class);
        $app->bind(WebhookApi::class, EnqueueWebhook::class);
    }

    public function boot(Application $app): void {}
}
