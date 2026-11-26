<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource\Pages;

use AIArmada\AffiliateNetwork\Features\Sites\Actions\CreateSite;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource;
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
