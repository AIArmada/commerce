<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\TicketTypes\Actions;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Ticketing\Enums\TicketAccessType;
use AIArmada\Ticketing\Enums\TicketTypeStatus;
use AIArmada\Ticketing\Enums\TicketTypeVisibility;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class SaveTicketType
{
    public function handle(Model $ticketable, array $data, ?TicketType $record = null): TicketType
    {
        $attributes = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'access_type' => ['required', Rule::enum(TicketAccessType::class)],
            'seating_mode' => ['nullable', Rule::enum(SeatingMode::class)],
            'price' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha:ascii'],
            'admits_quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'min_quantity' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'max_quantity' => ['nullable', 'integer', 'min:0', 'max:2147483647', Rule::when(isset($data['min_quantity']), 'gte:min_quantity')],
            'sales_starts_at' => ['nullable', 'date'],
            'sales_ends_at' => ['nullable', 'date', Rule::when(! empty($data['sales_starts_at']), 'after_or_equal:sales_starts_at')],
            'status' => ['required', Rule::enum(TicketTypeStatus::class)],
            'visibility' => ['required', Rule::enum(TicketTypeVisibility::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'metadata' => ['nullable', 'array'],
        ])->validate();

        return (new TicketType)->getConnection()->transaction(function () use ($ticketable, $record, $attributes): TicketType {
            if (! $ticketable->exists || ! $ticketable->newQuery()->whereKey($ticketable->getKey())->exists()) {
                throw new AuthorizationException('The ticketable record is not accessible.');
            }

            if (method_exists($ticketable, 'ownerScopeConfig') && ! OwnerUiScope::canMutateRecord($ticketable)) {
                throw new AuthorizationException('The ticketable record is not writable.');
            }

            if ($record === null) {
                OwnerUiScope::resolveOwner(TicketType::class);
                $type = new TicketType;
                $type->ticketable()->associate($ticketable);
            } else {
                $type = OwnerUiScope::apply(TicketType::query(), includeGlobal: false)
                    ->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

                if ($type->ticketable_type !== $ticketable->getMorphClass() || (string) $type->ticketable_id !== (string) $ticketable->getKey()) {
                    throw new AuthorizationException('A ticket type cannot be reassigned to another ticketable.');
                }
            }

            $type->fill($attributes);
            $type->saveOrFail();

            return $type;
        });
    }
}
