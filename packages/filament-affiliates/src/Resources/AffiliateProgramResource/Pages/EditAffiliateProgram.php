<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditAffiliateProgram extends EditRecord
{
    protected static string $resource = AffiliateProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
