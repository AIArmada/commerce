<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankResource\Pages;

use AIArmada\Affiliates\Features\Upline\Api\RankAdministration;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateRank;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateAffiliateRank extends CreateRecord
{
    protected static string $resource = AffiliateRankResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return AffiliateRank::query()->findOrFail(app(RankAdministration::class)->create($data));
    }
}
