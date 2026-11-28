<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrganizations\Resources\OrganizationResource\Pages;

use AIArmada\FilamentOrganizations\Resources\OrganizationResource;
use AIArmada\Organizations\Actions\UpdateOrganizationAction;
use AIArmada\Organizations\Models\Organization;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditOrganization extends EditRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Organization, 404);
        $actor = Filament::auth()->user();
        abort_unless($actor instanceof Model, 403);

        return app(UpdateOrganizationAction::class)->handle($record, $actor, $data);
    }
}
