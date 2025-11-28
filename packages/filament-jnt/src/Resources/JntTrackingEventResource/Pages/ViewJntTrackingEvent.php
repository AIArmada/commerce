<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Pages;

use AIArmada\FilamentJnt\Resources\JntTrackingEventResource;
use AIArmada\FilamentJnt\Resources\Pages\ReadOnlyViewRecord;
use Filament\Support\Icons\Heroicon;
use Override;

final class ViewJntTrackingEvent extends ReadOnlyViewRecord
{
    protected static string $resource = JntTrackingEventResource::class;

    #[Override]
    public function getTitle(): string
    {
        $record = $this->getRecord();

        return sprintf('Tracking %s', $record->tracking_number ?? $record->getKey());
    }

    public function getHeadingIcon(): Heroicon
    {
        return Heroicon::OutlinedMapPin;
    }
}
