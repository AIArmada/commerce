<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTemplateResource\Pages;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentEvents\Resources\EventTemplateResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewEventTemplate extends ViewRecord
{
    protected static string $resource = EventTemplateResource::class;

    public function boot(): void
    {
        OwnerContext::setForRequest(null);
    }
}
