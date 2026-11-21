<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Pages;

use AIArmada\AffiliateNetwork\Actions\CreateSite;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateAffiliateSite extends CreateRecord
{
    protected static string $resource = AffiliateSiteResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateSite::class)->execute($data);
    }
}
