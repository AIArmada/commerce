<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateCreativeResource\Pages;

use AIArmada\Affiliates\Features\Programs\Api\CreativeAdministration;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateCreativeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateAffiliateCreative extends CreateRecord
{
    protected static string $resource = AffiliateCreativeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $id = app(CreativeAdministration::class)->create(null, $data);

        return AffiliateCreativeResource::getEloquentQuery()->findOrFail($id);
    }
}
