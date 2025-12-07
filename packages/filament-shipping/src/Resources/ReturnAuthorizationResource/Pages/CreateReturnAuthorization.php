<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Pages;

use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReturnAuthorization extends CreateRecord
{
    protected static string $resource = ReturnAuthorizationResource::class;
}
