<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\CreatorPostbackResource\Pages;

use AIArmada\AffiliateNetwork\Features\Postbacks\Actions\ConfigureCreatorPostback;
use AIArmada\FilamentAffiliateNetwork\Resources\CreatorPostbackResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateCreatorPostback extends CreateRecord
{
    protected static string $resource = CreatorPostbackResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ConfigureCreatorPostback::class)->execute((string) $data['affiliate_id'], $data);
    }
}
