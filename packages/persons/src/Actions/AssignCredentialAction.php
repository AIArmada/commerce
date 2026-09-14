<?php

declare(strict_types=1);

namespace AIArmada\Persons\Actions;

use AIArmada\Persons\Enums\AssignmentStatus;
use AIArmada\Persons\Models\CredentialAssignment;
use AIArmada\Persons\Models\CredentialDefinition;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

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

        try {
            $assignment->save();
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

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
