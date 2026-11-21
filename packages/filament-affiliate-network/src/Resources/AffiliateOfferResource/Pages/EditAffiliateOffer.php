<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages;

use AIArmada\AffiliateNetwork\Actions\UpdateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditAffiliateOffer extends EditRecord
{
    protected static string $resource = AffiliateOfferResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var AffiliateOffer $record */
        $site = OwnerContext::withOwner(null, fn (): AffiliateSite => AffiliateSite::query()
            ->withoutOwnerScope()
            ->whereKey($record->site_id)
            ->firstOrFail());

        $owner = OwnerContext::fromTypeAndId($site->owner_type, $site->owner_id);

        return $this->record = OwnerContext::withOwner($owner, fn (): Model => app(UpdateOffer::class)->execute($record, $data));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
