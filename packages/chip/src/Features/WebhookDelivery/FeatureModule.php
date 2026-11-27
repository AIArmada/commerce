<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\WebhookDelivery;

use AIArmada\Chip\Features\WebhookDelivery\Actions\WebhooksApi;
use AIArmada\Chip\Features\WebhookDelivery\Api\PublicKeyCache;
use AIArmada\Chip\Features\WebhookDelivery\Api\WebhookRegistrationOperations;
use AIArmada\Chip\Features\WebhookDelivery\Persistence\CachePublicKey;
use AIArmada\Chip\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(WebhookRegistrationOperations::class, WebhooksApi::class);
        $app->bind(PublicKeyCache::class, CachePublicKey::class);
    }

    public function boot(Application $app): void {}
}
