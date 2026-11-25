<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Attribution\EntryPoints\Filament\Resources\AffiliateLinkResource\Pages;

use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkAdministration;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateLink;
use AIArmada\FilamentAffiliates\Features\Attribution\EntryPoints\Filament\Resources\AffiliateLinkResource;
use AIArmada\FilamentAffiliates\Features\Attribution\Support\TrackingLinkFormReferences;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditAffiliateLink extends EditRecord
{
    protected static string $resource = AffiliateLinkResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(TrackingLinkFormReferences::class)->validate($data);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $link = AffiliateLink::query()->whereKey($record->getKey())->firstOrFail();

        if ($link->affiliate !== null && ! $link->affiliate->isCommercialPurpose()) {
            unset($data['destination_url'], $data['tracking_url'], $data['custom_slug']);
        }

        app(TrackingLinkAdministration::class)->update((string) $link->getKey(), $data);

        return $link->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->authorize(fn (AffiliateLink $record): bool => AffiliateLinkResource::canDelete($record))
                ->visible(fn (AffiliateLink $record): bool => AffiliateLinkResource::canDelete($record))
                ->using(fn (AffiliateLink $record): bool => app(TrackingLinkAdministration::class)->delete((string) $record->getKey())),
        ];
    }
}
