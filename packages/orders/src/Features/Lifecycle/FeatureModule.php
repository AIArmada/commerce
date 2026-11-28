<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Lifecycle;

use AIArmada\Orders\Contracts\OrderAdministration;
use AIArmada\Orders\Contracts\OrderFulfillment;
use AIArmada\Orders\Contracts\OrderInvoiceNumber;
use AIArmada\Orders\Contracts\OrderMetadataWriter;
use AIArmada\Orders\Contracts\OrderNotes;
use AIArmada\Orders\Contracts\OrderServiceInterface;
use AIArmada\Orders\Features\Lifecycle\Actions\AssignOrderInvoiceNumber;
use AIArmada\Orders\Features\Lifecycle\Actions\ManageOrderNotes;
use AIArmada\Orders\Features\Lifecycle\Actions\ManageOrders;
use AIArmada\Orders\Features\Lifecycle\Actions\MergeOrderMetadata;
use AIArmada\Orders\Features\Lifecycle\Actions\OrderFulfillmentOperations;
use AIArmada\Orders\Features\Lifecycle\Actions\OrderIntake;
use AIArmada\Orders\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(OrderInvoiceNumber::class, AssignOrderInvoiceNumber::class);
        $app->bind(OrderNotes::class, ManageOrderNotes::class);
        $app->bind(OrderAdministration::class, ManageOrders::class);
        $app->bind(OrderFulfillment::class, OrderFulfillmentOperations::class);
        $app->bind(OrderMetadataWriter::class, MergeOrderMetadata::class);
        $app->bind(OrderServiceInterface::class, OrderIntake::class);
    }

    public function boot(Application $app): void {}
}
