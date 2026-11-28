<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Actions;

use AIArmada\Events\Contracts\EventImportScopeValidator;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Registrations\States\RegistrationStatus\RegistrationStatus;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

final readonly class ImportEventRegistrationAction
{
    public function __construct(
        private EventImportScopeValidator $scope,
        private RegistrationServiceInterface $registrations,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, mixed $actor): EventRegistration
    {
        $data = Validator::make($data, [
            'event_id' => ['required', 'uuid'],
            'event_occurrence_id' => ['nullable', 'uuid'],
            'registration_type' => ['required', 'string', 'max:64'],
            'status' => ['required', Rule::in(array_keys(RegistrationStatus::options()))],
            'source' => ['required', 'string', 'max:64'],
            'total_participants' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'total_amount' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:16'],
        ])->validate();

        $this->scope->validate($data['event_id'], $data['event_occurrence_id'] ?? null, $actor, 'manageAdmissions');

        $registrationClass = ModelResolver::registrationClass();
        Gate::forUser($actor)->authorize('create', $registrationClass);

        if (! isset($data['total_participants'])) {
            unset($data['total_participants']);
        }

        foreach (['total_participants', 'total_amount'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }

        try {
            $registrationId = $this->registrations->register($data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['registration' => $exception->getMessage()]);
        }

        $record = $registrationClass::query()->findOrFail($registrationId);

        if (! $record instanceof EventRegistration) {
            throw new LogicException('Configured registration model must extend EventRegistration.');
        }

        return $record;
    }
}
