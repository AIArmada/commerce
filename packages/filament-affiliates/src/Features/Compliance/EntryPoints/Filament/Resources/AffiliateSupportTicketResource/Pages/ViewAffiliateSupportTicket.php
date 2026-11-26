<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateSupportTicketResource\Pages;

use AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateSupportTicketResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewAffiliateSupportTicket extends ViewRecord
{
    protected static string $resource = AffiliateSupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
