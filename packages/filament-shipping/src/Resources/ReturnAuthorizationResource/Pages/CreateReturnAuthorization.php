<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Pages;

use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource;
use Filament\Resources\Pages\CreateRecord;
use AIArmada\Shipping\Features\Returns\Api\ManageReturnAuthorization;
use AIArmada\Shipping\Features\Returns\Models\ReturnAuthorization;
use Illuminate\Database\Eloquent\Model;

class CreateReturnAuthorization extends CreateRecord
{
    protected static string $resource = ReturnAuthorizationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return ReturnAuthorization::query()->findOrFail(app(ManageReturnAuthorization::class)->create($data));
    }
}
