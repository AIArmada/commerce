<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrganizations\Resources\OrganizationResource\Pages;

use AIArmada\FilamentOrganizations\Resources\OrganizationResource;
use AIArmada\Organizations\Models\Organization;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditOrganization extends EditRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Organization, 404);
        OrganizationResource::authorizeRecord($record, 'organization.update');

        $record->fill($data);
        $record->save();

        return $record;
    }
}
