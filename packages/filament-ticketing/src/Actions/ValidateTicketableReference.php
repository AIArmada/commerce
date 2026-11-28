<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Actions;

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

        if (! is_string($type) || $type === '' || (! is_string($id) && ! is_int($id)) || (string) $id === '') {
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

        $record = $query->whereKey((string) $id)->first();

        if (! $record instanceof Model) {
            throw ValidationException::withMessages([
                'ticketable' => 'The selected ticketable record is not accessible.',
            ]);
        }

        $data['ticketable_type'] = $record->getMorphClass();
        $data['ticketable_id'] = (string) $record->getKey();

        return $data;
    }
}
