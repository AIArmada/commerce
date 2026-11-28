<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Approvals;

use AIArmada\Docs\Contracts\DocumentApprovals;
use AIArmada\Docs\Features\Approvals\Actions\DecideDocApproval;
use AIArmada\Docs\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(DocumentApprovals::class, DecideDocApproval::class);
    }

    public function boot(Application $app): void {}
}
