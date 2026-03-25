<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalSegmentResource\Pages;

use AIArmada\FilamentSignals\Resources\SignalSegmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListSignalSegments extends ListRecords
{
    protected static string $resource = SignalSegmentResource::class;

    public function getTitle(): string
    {
        return 'Audience Segments';
    }

    public function getSubheading(): ?string
    {
        return 'Group visitors or sessions with reusable rules for reports, filters, and comparisons.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New audience segment'),
        ];
    }
}
