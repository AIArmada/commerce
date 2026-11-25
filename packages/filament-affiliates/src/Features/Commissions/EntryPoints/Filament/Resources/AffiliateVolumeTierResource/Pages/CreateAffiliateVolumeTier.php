<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateVolumeTierResource\Pages;

use AIArmada\Affiliates\Features\Commissions\Api\VolumeTierAdministration;
use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateVolumeTierResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateAffiliateVolumeTier extends CreateRecord
{
    protected static string $resource = AffiliateVolumeTierResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $id = app(VolumeTierAdministration::class)->create($data);

        return AffiliateVolumeTierResource::getEloquentQuery()->findOrFail($id);
    }
}
