<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Resources\UserResource\Pages;

use AIArmada\FilamentPermissions\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
