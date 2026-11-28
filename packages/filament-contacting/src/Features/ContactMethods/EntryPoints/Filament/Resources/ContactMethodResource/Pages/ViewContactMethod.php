<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Features\ContactMethods\EntryPoints\Filament\Resources\ContactMethodResource\Pages;

use AIArmada\FilamentContacting\Features\ContactMethods\EntryPoints\Filament\Resources\ContactMethodResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewContactMethod extends ViewRecord
{
    protected static string $resource = ContactMethodResource::class;
}
