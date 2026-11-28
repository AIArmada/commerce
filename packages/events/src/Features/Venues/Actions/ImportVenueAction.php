<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Venues\Actions;

use AIArmada\Addressing\Contracts\AddressAttachmentWriter;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Events\Features\Venues\Models\Venue;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class ImportVenueAction
{
    public function __construct(private AddressAttachmentWriter $addresses) {}

    /** @param array<string, mixed> $data */
    public function handle(?string $venueId, array $data, mixed $actor): Venue
    {
        if (! $actor instanceof Authenticatable) {
            throw new AuthorizationException;
        }

        if ($venueId !== null && ! Str::isUuid($venueId)) {
            throw ValidationException::withMessages(['venue_id' => 'A valid venue ID is required.']);
        }

        foreach (['address_id', 'phone', 'email', 'slug'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = mb_trim($data[$field]);
                if ($data[$field] === '') {
                    $data[$field] = null;
                }
            }
        }

        $required = $venueId === null ? 'required' : 'sometimes';
        $data = Validator::make($data, [
            'name' => [$required, 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'venue_type' => [$required, 'required', 'string', 'max:255'],
            'status' => [$required, 'required', 'string', 'max:255'],
            'address_id' => ['nullable', 'uuid'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:254'],
        ])->validate();

        return DB::transaction(function () use ($venueId, $data, $actor): Venue {
            $class = ModelResolver::venueClass();
            $venue = $venueId === null ? new $class : $class::query()->whereKey($venueId)->lockForUpdate()->firstOrFail();

            if (! $venue instanceof Venue) {
                throw new LogicException('Configured venue model must extend Venue.');
            }

            $subject = $venueId === null ? $class : $venue;

            Gate::forUser($actor)->authorize($venueId === null ? 'create' : 'update', $subject);

            if ($venueId === null) {
                $venue->visibility = 'public';
            }

            $venue->fill(Arr::only($data, ['name', 'slug', 'venue_type', 'status']));
            $venue->save();

            $addressId = $data['address_id'] ?? null;

            if (is_string($addressId) && $addressId !== '') {
                try {
                    $this->addresses->attach($addressId, $venue->getMorphClass(), (string) $venue->getKey(), 'primary', true);
                } catch (AuthorizationException | ModelNotFoundException) {
                    throw ValidationException::withMessages(['address_id' => 'The selected address does not exist in the current scope.']);
                }
            }

            foreach (['phone', 'email'] as $type) {
                $value = $data[$type] ?? null;

                if (! is_string($value) || mb_trim($value) === '') {
                    continue;
                }

                $venue->addContactMethod(new ContactMethodData(
                    type: $type,
                    value: mb_trim($value),
                    isPrimary: true,
                    isPublic: false,
                ));
            }

            return $venue;
        });
    }
}
