<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditAffiliateOffer extends EditRecord
{
    protected static string $resource = AffiliateOfferResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var AffiliateSite $site */
        $site = OwnerContext::withOwner(null, fn (): AffiliateSite => AffiliateSite::query()
            ->withoutOwnerScope()
            ->whereKey((string) $data['site_id'])
            ->firstOrFail());

        $data['site_id'] = (string) $site->getKey();

        if (isset($data['category_id']) && $data['category_id'] !== null && $data['category_id'] !== '') {
            /** @var AffiliateOfferCategory $category */
            $category = OwnerContext::withOwner(null, fn (): AffiliateOfferCategory => AffiliateOfferCategory::query()
                ->withoutOwnerScope()
                ->whereKey((string) $data['category_id'])
                ->firstOrFail());

            $data['category_id'] = (string) $category->getKey();
        }

        return $data;
    }

    /**
     * Wrap model update in explicit global owner context so the ScopesBySiteOwner
     * updating-hook does not throw when affiliate-network.owner.enabled=true.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return OwnerContext::withOwner(null, fn (): Model => parent::handleRecordUpdate($record, $data));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
