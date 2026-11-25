<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankResource\Pages;

use AIArmada\Affiliates\Features\Upline\Api\RankAdministration;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditAffiliateRank extends EditRecord
{
    protected static string $resource = AffiliateRankResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(RankAdministration::class)->update((string) $record->getKey(), $data);

        return $record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->authorize(fn (AffiliateRank $record): bool => AffiliateRankResource::canDelete($record))
                ->using(fn (AffiliateRank $record): bool => app(RankAdministration::class)->delete((string) $record->getKey())),
        ];
    }
}
