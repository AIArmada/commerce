<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateCreativeResource\Pages;

use AIArmada\Affiliates\Features\Programs\Api\CreativeAdministration;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateCreativeResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditAffiliateCreative extends EditRecord
{
    protected static string $resource = AffiliateCreativeResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(CreativeAdministration::class)->update(null, (string) $record->getKey(), $data);

        return AffiliateCreativeResource::getEloquentQuery()->findOrFail($record->getKey());
    }
}
