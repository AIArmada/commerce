<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Attribution\EntryPoints\Filament\Resources\AffiliateLinkResource\Pages;

use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkAdministration;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\FilamentAffiliates\Features\Attribution\EntryPoints\Filament\Resources\AffiliateLinkResource;
use AIArmada\FilamentAffiliates\Features\Attribution\Support\TrackingLinkFormReferences;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateAffiliateLink extends CreateRecord
{
    protected static string $resource = AffiliateLinkResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(TrackingLinkFormReferences::class)->validate($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $created = app(TrackingLinkAdministration::class)->create((string) $data['affiliate_id'], $data['destination_url'], $data);

        return AffiliateLink::query()->findOrFail($created->id);
    }

}
