<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Features\Snapshots\EntryPoints\Filament\Resources\ContactSnapshotResource\Pages;

use AIArmada\FilamentContacting\Features\Snapshots\EntryPoints\Filament\Resources\ContactSnapshotResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewContactSnapshot extends ViewRecord
{
    protected static string $resource = ContactSnapshotResource::class;
}
