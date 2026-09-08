<?php

declare(strict_types=1);

namespace AIArmada\Persons\Actions;

use AIArmada\Persons\Enums\AssignmentStatus;
use AIArmada\Persons\Models\CredentialAssignment;
use AIArmada\Persons\Models\CredentialDefinition;
use Illuminate\Database\Eloquent\Model;
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
            'status' => $attributes['status'] ?? AssignmentStatus::Active,
            ...$attributes,
        ]);
        $assignment->credential_id = $credential->getKey();
        $assignment->credentialable()->associate($credentialable);
        $assignment->save();

        return $assignment;
    }
}
