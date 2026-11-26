<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalSegmentResource\Pages;

use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalSegmentResource;
use AIArmada\Signals\Features\Reporting\Api\SegmentAdministration;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditSignalSegment extends EditRecord
{
    protected static string $resource = SignalSegmentResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(SegmentAdministration::class)->update((string) $record->getKey(), $data);

        return $record->refresh();
    }

    public function getTitle(): string
    {
        return 'Edit audience segment';
    }

    public function getSubheading(): ?string
    {
        return 'Update the rules or description for this saved audience segment.';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
