<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Resources\TicketTypeResource\Pages;

use AIArmada\FilamentTicketing\Resources\TicketTypeResource;
use AIArmada\FilamentTicketing\Support\TicketableReferenceGuard;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditTicketType extends EditRecord
{
    protected static string $resource = TicketTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(TicketableReferenceGuard::class)->sanitize($data);
    }
}
