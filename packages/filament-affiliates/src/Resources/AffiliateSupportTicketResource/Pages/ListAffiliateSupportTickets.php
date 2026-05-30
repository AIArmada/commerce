<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateSupportTicketResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateSupportTicketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateSupportTickets extends ListRecords
{
    protected static string $resource = AffiliateSupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
