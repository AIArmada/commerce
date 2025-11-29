<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewAffiliatePayout extends ViewRecord
{
    protected static string $resource = AffiliatePayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back')
                ->url(fn (): string => AffiliatePayoutResource::getUrl())
                ->color('gray'),
        ];
    }
}
