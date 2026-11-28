<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Credentials\Actions;

use AIArmada\Persons\Enums\AssignmentStatus;
use AIArmada\Persons\Features\Credentials\Models\CredentialAssignment;
use AIArmada\Persons\Features\Credentials\Models\CredentialDefinition;
use AIArmada\Persons\Models\Person;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;
use RuntimeException;

final class AssignCredentialAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Model $credentialable, string $credentialId, array $attributes = []): CredentialAssignment
    {
        if (! $credentialable->exists || $credentialable->getKey() === null) {
            throw new InvalidArgumentException('Credentials can only be assigned to a persisted model.');
        }

        $credential = CredentialDefinition::query()->findOrFail($credentialId);
        $validated = $this->validate($attributes);

        $existing = CredentialAssignment::query()
            ->where('credentialable_type', $credentialable->getMorphClass())
            ->where('credentialable_id', $credentialable->getKey())
            ->where('credential_id', $credential->getKey())
            ->first();

        if ($existing instanceof CredentialAssignment) {
            return $existing;
        }

        $assignment = new CredentialAssignment;
        $assignment->fill([
            'status' => AssignmentStatus::Active,
            ...$validated,
        ]);
        $assignment->credential_id = $credential->getKey();
        $assignment->credentialable()->associate($credentialable);
        $this->assertDates($assignment);

        try {
            $assignment->getConnection()->transaction(function () use ($assignment): void {
                if (! $assignment->save()) {
                    throw new RuntimeException('Assignment creation was cancelled.');
                }
            });
        } catch (UniqueConstraintViolationException $exception) {
            $raced = CredentialAssignment::query()
                ->where('credentialable_type', $credentialable->getMorphClass())
                ->where('credentialable_id', $credentialable->getKey())
                ->where('credential_id', $credential->getKey())
                ->first();

            if (! $raced instanceof CredentialAssignment) {
                throw $exception;
            }

            return $raced;
        }

        return $assignment;
    }

    /** @param array<string, mixed> $attributes */
    public function update(Person $person, CredentialAssignment $assignment, array $attributes): CredentialAssignment
    {
        return $person->getConnection()->transaction(function () use ($person, $assignment, $attributes): CredentialAssignment {
            $record = $this->lockAssignment($person, $assignment);
            $reference = $attributes['credential_id'] ?? $record->credential_id;
            unset($attributes['credential_id']);

            if (! is_string($reference) || mb_trim($reference) === '') {
                throw new InvalidArgumentException('A valid assignment reference is required.');
            }

            $record->fill($this->validate($attributes));
            $record->credential_id = $reference;
            $this->assertDates($record);

            if (! $record->save()) {
                throw new RuntimeException('Assignment update was cancelled.');
            }

            return $record;
        });
    }

    public function delete(Person $person, CredentialAssignment $assignment): bool
    {
        return $person->getConnection()->transaction(function () use ($person, $assignment): bool {
            return (bool) $this->lockAssignment($person, $assignment)->delete();
        });
    }

    private function lockAssignment(Person $person, CredentialAssignment $assignment): CredentialAssignment
    {
        if (! $person->exists || $person->getKey() === null || ! $assignment->exists || $assignment->getKey() === null) {
            throw new InvalidArgumentException('Person and assignment must be persisted.');
        }

        $person->newQuery()->whereKey($person->getKey())->lockForUpdate()->firstOrFail();

        $record = CredentialAssignment::query()
            ->where('credentialable_type', $person->getMorphClass())
            ->where('credentialable_id', $person->getKey())
            ->whereKey($assignment->getKey())
            ->lockForUpdate()
            ->first();

        if ($record === null) {
            throw new AuthorizationException('The assignment does not belong to this person.');
        }

        return $record;
    }

    private function assertDates(CredentialAssignment $assignment): void
    {
        if ($assignment->date_obtained !== null && $assignment->date_expired !== null
            && $assignment->date_expired->lt($assignment->date_obtained)) {
            throw new InvalidArgumentException('The expiry date must be on or after the start date.');
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function validate(array $attributes): array
    {
        $allowed = ['status', 'issuing_institution_id', 'registration_number', 'date_obtained', 'date_expired'];
        $unknown = array_diff(array_keys($attributes), $allowed);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown credential assignment attributes: ' . implode(', ', $unknown) . '.');
        }

        $status = $attributes['status'] ?? AssignmentStatus::Active;

        if (! $status instanceof AssignmentStatus && AssignmentStatus::tryFrom((string) $status) === null) {
            throw new InvalidArgumentException('Credential assignment status must be a valid assignment status value.');
        }

        foreach (['date_obtained', 'date_expired'] as $key) {
            $value = $attributes[$key] ?? null;

            if ($value !== null && ! $value instanceof DateTimeInterface && ! is_string($value)) {
                throw new InvalidArgumentException("Credential assignment {$key} must be a date string or DateTime instance.");
            }
        }

        $registrationNumber = $attributes['registration_number'] ?? null;

        if ($registrationNumber !== null && (! is_string($registrationNumber) || mb_strlen($registrationNumber) > 100)) {
            throw new InvalidArgumentException('Credential assignment registration number must be a string of at most 100 characters.');
        }

        return $attributes;
    }
}
