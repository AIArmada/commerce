<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\ClientResource\Pages;

use AIArmada\FilamentChip\Resources\ClientResource;
use AIArmada\FilamentChip\Resources\Pages\ReadOnlyListRecords;
use Override;

final class ListClients extends ReadOnlyListRecords
{
    protected static string $resource = ClientResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'Clients';
    }

    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
