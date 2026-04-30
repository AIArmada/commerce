<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditAffiliateOffer extends EditRecord
{
    protected static string $resource = AffiliateOfferResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (config('affiliate-network.owner.enabled', false)) {
            /** @var AffiliateSite $site */
            $site = OwnerWriteGuard::findOrFailForOwner(
                AffiliateSite::class,
                (string) $data['site_id'],
                includeGlobal: false,
                message: 'Site is not accessible in the current owner scope.',
            );
        } else {
            $site = AffiliateSite::query()->whereKey((string) $data['site_id'])->firstOrFail();
        }

        $data['site_id'] = (string) $site->getKey();

        if (isset($data['category_id']) && $data['category_id'] !== null && $data['category_id'] !== '') {
            if (config('affiliate-network.owner.enabled', false)) {
                /** @var AffiliateOfferCategory $category */
                $category = OwnerWriteGuard::findOrFailForOwner(
                    AffiliateOfferCategory::class,
                    (string) $data['category_id'],
                    includeGlobal: (bool) config('affiliate-network.owner.include_global', false),
                    message: 'Category is not accessible in the current owner scope.',
                );
            } else {
                $category = AffiliateOfferCategory::query()->whereKey((string) $data['category_id'])->firstOrFail();
            }

            $data['category_id'] = (string) $category->getKey();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
