<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewAffiliateConversion extends ViewRecord
{
    protected static string $resource = AffiliateConversionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back')
                ->url(fn (): string => AffiliateConversionResource::getUrl())
                ->color('gray'),
        ];
    }
}
