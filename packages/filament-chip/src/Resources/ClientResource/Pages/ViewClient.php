<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\ClientResource\Pages;

use AIArmada\FilamentChip\Resources\ClientResource;
use AIArmada\FilamentChip\Resources\Pages\ReadOnlyViewRecord;
use Override;

final class ViewClient extends ReadOnlyViewRecord
{
    protected static string $resource = ClientResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'Client Details';
    }
}
