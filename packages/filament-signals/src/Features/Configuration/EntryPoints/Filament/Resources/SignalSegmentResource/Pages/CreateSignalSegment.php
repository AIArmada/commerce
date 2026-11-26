<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalSegmentResource\Pages;

use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalSegmentResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateSignalSegment extends CreateRecord
{
    protected static string $resource = SignalSegmentResource::class;

    public function getTitle(): string
    {
        return 'Create audience segment';
    }

    public function getSubheading(): ?string
    {
        return 'Use simple rules to group people or sessions you want to analyze together later.';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
