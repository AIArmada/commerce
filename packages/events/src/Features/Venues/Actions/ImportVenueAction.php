<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Venues\Actions;

use AIArmada\Addressing\Contracts\AddressAttachmentWriter;
use AIArmada\Contacting\Actions\CreateContactMethodAction;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Events\Features\Venues\Models\Venue;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class ImportVenueAction
{
    public function __construct(
        private AddressAttachmentWriter $addresses,
        private CreateContactMethodAction $contacts,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(?string $venueId, array $data, mixed $actor): Venue
    {
        if (! $actor instanceof Authenticatable) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($venueId, $data, $actor): Venue {
            $class = ModelResolver::venueClass();
            $venue = $venueId === null ? new $class : $class::query()->whereKey($venueId)->lockForUpdate()->firstOrFail();

            if (! $venue instanceof Venue) {
                throw new LogicException('Configured venue model must extend Venue.');
            }

            $subject = $venueId === null ? $class : $venue;

            Gate::forUser($actor)->authorize($venueId === null ? 'create' : 'update', $subject);

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

                $this->contacts->execute($venue, new ContactMethodData(
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
