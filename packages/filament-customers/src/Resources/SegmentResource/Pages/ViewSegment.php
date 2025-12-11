<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\SegmentResource\Pages;

use AIArmada\FilamentCustomers\Resources\SegmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSegment extends ViewRecord
{
    protected static string $resource = SegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
