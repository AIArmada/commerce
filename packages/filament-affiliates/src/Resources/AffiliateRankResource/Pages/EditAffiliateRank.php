<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateRankResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateRankResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditAffiliateRank extends EditRecord
{
    protected static string $resource = AffiliateRankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
