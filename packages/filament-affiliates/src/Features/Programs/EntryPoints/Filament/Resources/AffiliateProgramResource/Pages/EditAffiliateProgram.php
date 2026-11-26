<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource\Pages;

use AIArmada\Affiliates\Features\Programs\Api\ProgramAdministration;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditAffiliateProgram extends EditRecord
{
    protected static string $resource = AffiliateProgramResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(ProgramAdministration::class)->update((string) $record->getKey(), $data);

        return $record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()->action(fn (Model $record) => app(ProgramAdministration::class)->delete((string) $record->getKey())),
        ];
    }
}
