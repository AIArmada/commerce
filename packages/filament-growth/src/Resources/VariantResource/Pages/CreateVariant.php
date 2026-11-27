<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources\VariantResource\Pages;

use AIArmada\Growth\Actions\SaveVariant;
use AIArmada\Growth\Models\Variant;
use AIArmada\FilamentGrowth\Resources\VariantResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class CreateVariant extends CreateRecord
{
    protected static string $resource = VariantResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        Gate::authorize('create', Variant::class);

        return app(SaveVariant::class)->create($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
