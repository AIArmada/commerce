<?php

declare(strict_types=1);

namespace AIArmada\FilamentLinks\Resources\LinkResource\Pages;

use AIArmada\FilamentLinks\Resources\LinkResource;
use AIArmada\Links\Actions\CreateLink as CreateLinkAction;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateLink extends CreateRecord
{
    protected static string $resource = LinkResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return CreateLinkAction::run($data);
    }
}
