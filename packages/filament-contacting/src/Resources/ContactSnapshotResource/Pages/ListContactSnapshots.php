<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Resources\ContactSnapshotResource\Pages;

use AIArmada\FilamentContacting\Resources\ContactSnapshotResource;
use Filament\Resources\Pages\ListRecords;

final class ListContactSnapshots extends ListRecords
{
    protected static string $resource = ContactSnapshotResource::class;
}
