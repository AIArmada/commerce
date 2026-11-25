<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateVolumeTierResource\Pages;

use AIArmada\Affiliates\Features\Commissions\Api\VolumeTierAdministration;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateVolumeTier;
use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateVolumeTierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditAffiliateVolumeTier extends EditRecord
{
    protected static string $resource = AffiliateVolumeTierResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(VolumeTierAdministration::class)->update((string) $record->getKey(), $data);

        return $record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->authorize(fn (AffiliateVolumeTier $record): bool => AffiliateVolumeTierResource::canDelete($record))
                ->using(fn (AffiliateVolumeTier $record): bool => app(VolumeTierAdministration::class)->delete((string) $record->getKey())),
        ];
    }
}
