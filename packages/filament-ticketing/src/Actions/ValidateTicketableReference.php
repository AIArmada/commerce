<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Actions;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Ticketing\Support\TicketableTypeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;

final readonly class ValidateTicketableReference
{
    public function __construct(private TicketableTypeRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        $type = $data['ticketable_type'] ?? null;
        $id = $data['ticketable_id'] ?? null;

        if (! is_string($type) || $type === '' || ! is_scalar($id) || (string) $id === '') {
            throw ValidationException::withMessages([
                'ticketable' => 'Select a ticketable record.',
            ]);
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        if (! in_array($class, $this->registry->all(), true)) {
            throw ValidationException::withMessages([
                'ticketable' => 'The selected ticketable type is not allowed.',
            ]);
        }

        /** @var Builder<Model> $query */
        $query = $class::query();

        $exists = OwnerUiScope::apply($query, includeGlobal: false)
            ->whereKey((string) $id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'ticketable' => 'The selected ticketable record is not accessible.',
            ]);
        }

        return $data;
    }
}
