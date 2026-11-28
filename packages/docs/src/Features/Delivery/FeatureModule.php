<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Delivery;

use AIArmada\Docs\Contracts\DocumentEmailSender;
use AIArmada\Docs\Contracts\DocumentSharing;
use AIArmada\Docs\Features\Delivery\Actions\ManageDocShareLinks;
use AIArmada\Docs\Features\Delivery\Actions\DocEmailService;
use AIArmada\Docs\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(DocumentEmailSender::class, DocEmailService::class);
        $app->bind(DocumentSharing::class, ManageDocShareLinks::class);
    }

    public function boot(Application $app): void {}
}
