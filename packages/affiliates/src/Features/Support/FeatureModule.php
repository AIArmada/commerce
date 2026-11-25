<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Support;

final class FeatureModule implements \AIArmada\Affiliates\Platform\FeatureModule
{
    public function register(\Illuminate\Contracts\Foundation\Application $app): void
    {
        $app->bind(Api\SupportTicketApi::class, Queries\SupportTicketGateway::class);
    }

    public function boot(\Illuminate\Contracts\Foundation\Application $app): void {}
}
