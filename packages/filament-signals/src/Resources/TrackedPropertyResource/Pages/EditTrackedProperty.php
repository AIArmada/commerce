<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\TrackedPropertyResource\Pages;

use AIArmada\FilamentSignals\Resources\TrackedPropertyResource;
use Filament\Resources\Pages\EditRecord;

final class EditTrackedProperty extends EditRecord
{
    protected static string $resource = TrackedPropertyResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
