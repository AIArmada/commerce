<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Resources\TicketTypeResource\Pages;

use AIArmada\FilamentTicketing\Resources\TicketTypeResource;
use AIArmada\FilamentTicketing\Actions\ValidateTicketableReference;
use AIArmada\Ticketing\Features\TicketTypes\Actions\SaveTicketType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Filament\Resources\Pages\CreateRecord;

final class CreateTicketType extends CreateRecord
{
    protected static string $resource = TicketTypeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        abort_unless(TicketTypeResource::canCreate(), 403);

        $data = app(ValidateTicketableReference::class)->handle($data);
        $class = Relation::getMorphedModel($data['ticketable_type']) ?? $data['ticketable_type'];
        $host = $class::query()->findOrFail($data['ticketable_id']);

        return app(SaveTicketType::class)->handle($host, $data);
    }
}
