<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource\Pages;

use AIArmada\AffiliateNetwork\Features\Postbacks\Api\PostbacksApi;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateCreatorPostback extends CreateRecord
{
    protected static string $resource = CreatorPostbackResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $created = app(PostbacksApi::class)->configure((string) $data['affiliate_id'], $data);

        return CreatorPostbackEndpoint::query()->findOrFail($created->id);
    }
}
