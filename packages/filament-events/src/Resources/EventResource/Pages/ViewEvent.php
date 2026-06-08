<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\Pages;

use AIArmada\FilamentEvents\Resources\EventResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EventResource::submitForReviewAction(),
            EventResource::approveAction(),
            EventResource::requestChangesAction(),
            EventResource::rejectEventAction(),
            Actions\EditAction::make(),
        ];
    }
}
