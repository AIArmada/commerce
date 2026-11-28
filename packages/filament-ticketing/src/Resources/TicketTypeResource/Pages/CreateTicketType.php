<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Resources\TicketTypeResource\Pages;

use AIArmada\FilamentTicketing\Resources\TicketTypeResource;
use AIArmada\FilamentTicketing\Actions\ValidateTicketableReference;
use Filament\Resources\Pages\CreateRecord;

final class CreateTicketType extends CreateRecord
{
    protected static string $resource = TicketTypeResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(ValidateTicketableReference::class)->handle($data);
    }
}
