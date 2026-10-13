<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralProgramResource\Pages;

use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\ReferralProgramResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewReferralProgram extends ViewRecord
{
    protected static string $resource = ReferralProgramResource::class;

    /**
     * @return array<int, EditAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
