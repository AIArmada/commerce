<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\TitleResource\Pages;

use AIArmada\FilamentPersons\Resources\TitleResource;
use AIArmada\Persons\Actions\ReorderTitleAction;
use AIArmada\Persons\Models\Title;
use Filament\Resources\Pages\CreateRecord;

final class CreateTitle extends CreateRecord
{
    protected static string $resource = TitleResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Title
    {
        return app(ReorderTitleAction::class)->create($data);
    }
}
