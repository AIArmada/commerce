<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\Pages;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentEvents\Resources\EventResource;
use Filament\Resources\Pages\ListRecords;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    public function boot(): void
    {
        OwnerContext::setForRequest(null);
    }
}
