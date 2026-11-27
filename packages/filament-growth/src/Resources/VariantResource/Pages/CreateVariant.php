<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources\VariantResource\Pages;

use AIArmada\FilamentGrowth\Actions\SaveVariant;
use AIArmada\FilamentGrowth\Resources\VariantResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateVariant extends CreateRecord
{
    protected static string $resource = VariantResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveVariant::class)->create($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
