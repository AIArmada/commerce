<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Support;

use AIArmada\Affiliates\Contracts\AffiliateRemovalGuard;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements \AIArmada\Affiliates\Platform\FeatureModule
{
    public function register(Application $app): void
    {
        $app->bind(Api\SupportTicketApi::class, Queries\SupportTicketGateway::class);
        $app->tag([Queries\SupportAffiliateRemovalGuard::class], AffiliateRemovalGuard::class);
    }

    public function boot(Application $app): void {}
}
