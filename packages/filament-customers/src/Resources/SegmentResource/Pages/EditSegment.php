<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\SegmentResource\Pages;

use AIArmada\FilamentCustomers\Resources\SegmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSegment extends EditRecord
{
    protected static string $resource = SegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
