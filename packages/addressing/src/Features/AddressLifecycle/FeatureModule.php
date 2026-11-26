<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\AddressLifecycle;

use AIArmada\Addressing\Contracts\AddressSnapshotWriter;
use AIArmada\Addressing\Contracts\AddressAttachmentWriter;
use AIArmada\Addressing\Features\AddressLifecycle\Actions\CreateAddressSnapshotAction;
use AIArmada\Addressing\Features\AddressLifecycle\Actions\UpdateAddressAttachmentAction;
use AIArmada\Addressing\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(AddressSnapshotWriter::class, CreateAddressSnapshotAction::class);
        $app->singleton(AddressAttachmentWriter::class, UpdateAddressAttachmentAction::class);
    }

    public function boot(Application $app): void {}
}
