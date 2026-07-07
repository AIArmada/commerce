<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\Pages;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentEvents\Resources\EventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    public function boot(): void
    {
        OwnerContext::setForRequest(null);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
