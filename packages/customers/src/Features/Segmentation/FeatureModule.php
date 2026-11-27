<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation;

use AIArmada\Customers\Features\Segmentation\Actions\AssignCustomerToSegment;
use AIArmada\Customers\Features\Segmentation\Actions\DetachDeletedSegmentMemberships;
use AIArmada\Customers\Features\Segmentation\Actions\EvaluateCustomerSegments;
use AIArmada\Customers\Features\Segmentation\Actions\RebuildAllSegments;
use AIArmada\Customers\Features\Segmentation\Actions\RemoveCustomerFromSegment;
use AIArmada\Customers\Features\Segmentation\Actions\SyncManualSegmentMemberships;
use AIArmada\Customers\Features\Segmentation\Actions\TransferSegmentMemberships;
use AIArmada\Customers\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(AssignCustomerToSegment::class);
        $app->bind(RemoveCustomerFromSegment::class);
        $app->bind(RebuildAllSegments::class);
        $app->bind(EvaluateCustomerSegments::class);
        $app->bind(SyncManualSegmentMemberships::class);
        $app->bind(TransferSegmentMemberships::class);
        $app->bind(DetachDeletedSegmentMemberships::class);
    }

    public function boot(Application $app): void {}
}
