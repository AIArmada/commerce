<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalSegmentResource\Pages;

use AIArmada\FilamentSignals\Resources\SignalSegmentResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateSignalSegment extends CreateRecord
{
    protected static string $resource = SignalSegmentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
