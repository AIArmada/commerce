<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\Pages;

use AIArmada\FilamentEvents\Pages\EventPublicPreview;
use AIArmada\FilamentEvents\Resources\EventResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publicPreview')
                ->label('Public Preview')
                ->icon('heroicon-o-eye')
                ->url(fn () => EventPublicPreview::getUrl(['event' => $this->record->getKey()])),
        ];
    }
}
