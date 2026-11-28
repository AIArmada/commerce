<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Attendance\Actions;

use AIArmada\Events\Contracts\EventAdministrativeCheckIn;
use AIArmada\Events\Contracts\EventCheckInService;
use AIArmada\Events\Data\EventCheckInResult;
use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Ticketing\Models\Pass;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class DefaultEventAdministrativeCheckIn implements EventAdministrativeCheckIn
{
    public function __construct(
        private CatalogApi $catalog,
        private RegistrationsApi $registrations,
        private EventCheckInService $checkIn,
    ) {}

    public function checkInPass(string $passId, mixed $actor): EventCheckInResult
    {
        $this->assertActor($actor);

        if (! Str::isUuid($passId)) {
            throw ValidationException::withMessages(['pass_id' => 'Invalid pass.']);
        }

        return DB::transaction(function () use ($passId, $actor): EventCheckInResult {
            $pass = Pass::query()->whereKey($passId)
                ->where('registration_type', $this->registrations->registrationMorphClass())
                ->firstOrFail();

            $registration = $pass->registration_id === null
                ? null
                : $this->registrations->summarize((string) $pass->registration_id);

            if ($registration === null) {
                throw ValidationException::withMessages(['pass_id' => 'The selected pass has no accessible event registration.']);
            }

            $this->authorize($registration->eventId, $actor);

            return $this->checkIn->checkInWithResult([
                ...$this->actorFields($actor),
                'event_id' => $registration->eventId,
                'event_occurrence_id' => $pass->occurrence_id ?? $registration->occurrenceId,
                'pass_id' => $passId,
                'event_registration_id' => $registration->id,
                'attendance_type' => 'registered',
                'check_in_source' => 'admin',
            ]);
        });
    }

    public function checkInWalkIn(string $eventId, mixed $actor, ?string $name = null, ?string $email = null): EventCheckInResult
    {
        $this->assertActor($actor);
        Validator::make(['event_id' => $eventId, 'name' => $name, 'email' => $email], [
            'event_id' => ['required', 'uuid'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($eventId, $actor, $name, $email): EventCheckInResult {
            $this->authorize($eventId, $actor);

            return $this->checkIn->checkInWithResult([
                ...$this->actorFields($actor),
                'event_id' => $eventId,
                'attendance_type' => 'walk_in',
                'check_in_source' => 'admin',
                'metadata' => ['name' => $name, 'email' => $email],
            ]);
        });
    }

    private function assertActor(mixed $actor): void
    {
        if (! $actor instanceof Authenticatable) {
            throw new AuthorizationException('An authenticated operator is required.');
        }
    }

    private function authorize(string $eventId, mixed $actor): void
    {
        EventWriteGuard::findOrFail($eventId);
        $this->catalog->authorizeManageAdmissions($actor, $eventId);
    }

    /** @return array<string, mixed> */
    private function actorFields(mixed $actor): array
    {
        if (! $actor instanceof Model) {
            return [];
        }

        return [
            'performed_by_type' => $actor->getMorphClass(),
            'performed_by_id' => (string) $actor->getKey(),
        ];
    }
}
