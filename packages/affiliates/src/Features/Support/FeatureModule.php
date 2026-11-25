<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Support;

use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements \AIArmada\Affiliates\Platform\FeatureModule
{
    public function register(Application $app): void
    {
        $app->bind(Api\SupportTicketApi::class, Queries\SupportTicketGateway::class);
    }

    public function boot(Application $app): void {}
}
