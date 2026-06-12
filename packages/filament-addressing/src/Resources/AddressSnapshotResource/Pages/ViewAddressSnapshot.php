<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressSnapshotResource\Pages;

use AIArmada\FilamentAddressing\Resources\AddressSnapshotResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewAddressSnapshot extends ViewRecord
{
    protected static string $resource = AddressSnapshotResource::class;
}
