<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages;

use AIArmada\AffiliateNetwork\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateAffiliateOffer extends CreateRecord
{
    protected static string $resource = AffiliateOfferResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var AffiliateSite $site */
        $site = OwnerContext::withOwner(null, fn (): AffiliateSite => AffiliateSite::query()
            ->withoutOwnerScope()
            ->whereKey((string) $data['site_id'])
            ->firstOrFail());

        $owner = OwnerContext::fromTypeAndId($site->owner_type, $site->owner_id);

        return OwnerContext::withOwner($owner, fn (): Model => app(CreateOffer::class)->execute($site, $data));
    }
}
