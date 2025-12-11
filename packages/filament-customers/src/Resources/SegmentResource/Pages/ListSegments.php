<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\SegmentResource\Pages;

use AIArmada\FilamentCustomers\Resources\SegmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSegments extends ListRecords
{
    protected static string $resource = SegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
