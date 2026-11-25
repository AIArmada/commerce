<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages;

use AIArmada\AffiliateNetwork\Features\Catalog\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SiteRecord;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Schemas\AffiliateOfferForm;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class CreateAffiliateOffer extends CreateRecord
{
    protected static string $resource = AffiliateOfferResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data = AffiliateOfferForm::normalizeData($data);
        $siteId = (string) $data['site_id'];
        $site = app(SitesApi::class)->findByIdUnscoped($siteId);

        if (! $site instanceof SiteRecord) {
            throw (new ModelNotFoundException)->setModel($siteId);
        }

        $owner = OwnerContext::fromTypeAndId($site->ownerType, $site->ownerId);

        return OwnerContext::withOwner($owner, fn (): Model => app(CreateOffer::class)->execute($site, $data));
    }
}
