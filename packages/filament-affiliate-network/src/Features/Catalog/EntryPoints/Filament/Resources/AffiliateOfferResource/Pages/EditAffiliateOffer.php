<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\Pages;

use AIArmada\AffiliateNetwork\Features\Catalog\Actions\UpdateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\Schemas\AffiliateOfferForm;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditAffiliateOffer extends EditRecord
{
    protected static string $resource = AffiliateOfferResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $data = AffiliateOfferForm::normalizeData($data);

        /** @var AffiliateOffer $record */
        $site = app(SitesApi::class)->findByIdUnscoped((string) $record->site_id);
        $owner = $site === null
            ? null
            : OwnerContext::fromTypeAndId($site->ownerType, $site->ownerId);

        return $this->record = OwnerContext::withOwner($owner, fn (): Model => app(UpdateOffer::class)->execute($record, $data));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
