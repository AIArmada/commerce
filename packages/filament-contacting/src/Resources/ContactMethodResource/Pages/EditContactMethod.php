<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Resources\ContactMethodResource\Pages;

use AIArmada\Contacting\Features\ContactMethods\Actions\UpdateContactMethodAction;
use AIArmada\Contacting\Features\ContactMethods\Models\ContactMethod;
use AIArmada\FilamentContacting\Resources\ContactMethodResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditContactMethod extends EditRecord
{
    protected static string $resource = ContactMethodResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['country_code'] = isset($data['country_code'])
            ? mb_strtoupper((string) $data['country_code'])
            : null;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof ContactMethod, 404);

        return app(UpdateContactMethodAction::class)->execute($record, $data);
    }
}
