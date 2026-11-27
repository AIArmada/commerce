<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Features\Snapshots\EntryPoints\Filament\Resources\ContactSnapshotResource\Pages;

use AIArmada\FilamentContacting\Features\Snapshots\EntryPoints\Filament\Resources\ContactSnapshotResource;
use Filament\Resources\Pages\ListRecords;

final class ListContactSnapshots extends ListRecords
{
    protected static string $resource = ContactSnapshotResource::class;
}
