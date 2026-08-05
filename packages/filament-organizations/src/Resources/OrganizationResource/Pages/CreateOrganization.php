<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrganizations\Resources\OrganizationResource\Pages;

use AIArmada\FilamentOrganizations\Resources\OrganizationResource;
use AIArmada\Organizations\Actions\CreateOrganizationAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateOrganization extends CreateRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = Filament::auth()->user();

        abort_unless($actor instanceof Model, 403);

        return app(CreateOrganizationAction::class)->handle($actor, $data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Organization created';
    }
}
