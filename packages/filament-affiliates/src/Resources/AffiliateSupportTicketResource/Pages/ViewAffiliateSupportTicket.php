<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateSupportTicketResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateSupportTicketResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewAffiliateSupportTicket extends ViewRecord
{
    protected static string $resource = AffiliateSupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
