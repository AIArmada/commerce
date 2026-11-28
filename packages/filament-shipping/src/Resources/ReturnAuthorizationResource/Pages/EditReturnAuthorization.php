<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Pages;

use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource;
use AIArmada\Shipping\Features\Returns\Api\ManageReturnAuthorization;
use AIArmada\Shipping\Features\Returns\Models\ReturnAuthorization;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditReturnAuthorization extends EditRecord
{
    protected static string $resource = ReturnAuthorizationResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(ManageReturnAuthorization::class)->update((string) $record->getKey(), $data);

        return $record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->using(fn (ReturnAuthorization $record): bool => app(ManageReturnAuthorization::class)->delete((string) $record->getKey())),
        ];
    }
}
