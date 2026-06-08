<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\OccurrenceResource\Pages;

use AIArmada\FilamentEvents\Resources\OccurrenceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditOccurrence extends EditRecord
{
    protected static string $resource = OccurrenceResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return OccurrenceResource::normalizeMutationData($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
