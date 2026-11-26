<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Contracts;

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressSnapshot;
use Illuminate\Database\Eloquent\Model;

interface AddressSnapshotWriter
{
    public function execute(Model $snapshotable, Address | AddressData $address, ?string $reason = null, ?string $label = null): AddressSnapshot;
}
