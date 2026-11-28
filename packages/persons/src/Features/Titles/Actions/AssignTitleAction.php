<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Titles\Actions;

use AIArmada\Persons\Enums\AssignmentStatus;
use AIArmada\Persons\Features\Titles\Models\Title;
use AIArmada\Persons\Features\Titles\Models\TitleAssignment;
use AIArmada\Persons\Models\Person;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;
use RuntimeException;

final class AssignTitleAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Model $titleable, string $titleId, array $attributes = []): TitleAssignment
    {
        if (! $titleable->exists || $titleable->getKey() === null) {
            throw new InvalidArgumentException('Titles can only be assigned to a persisted model.');
        }

        $title = Title::query()->findOrFail($titleId);
        $validated = $this->validate($attributes);

        $existing = TitleAssignment::query()
            ->where('titleable_type', $titleable->getMorphClass())
            ->where('titleable_id', $titleable->getKey())
            ->where('title_id', $title->getKey())
            ->first();

        if ($existing instanceof TitleAssignment) {
            return $existing;
        }

        $assignment = new TitleAssignment;
        $assignment->fill([
            'status' => AssignmentStatus::Active,
            ...$validated,
        ]);
        $assignment->title_id = $title->getKey();
        $assignment->titleable()->associate($titleable);
        $this->assertDates($assignment);

        try {
            $assignment->getConnection()->transaction(function () use ($assignment): void {
                if (! $assignment->save()) {
                    throw new RuntimeException('Assignment creation was cancelled.');
                }
            });
        } catch (UniqueConstraintViolationException $exception) {
            $raced = TitleAssignment::query()
                ->where('titleable_type', $titleable->getMorphClass())
                ->where('titleable_id', $titleable->getKey())
                ->where('title_id', $title->getKey())
                ->first();

            if (! $raced instanceof TitleAssignment) {
                throw $exception;
            }

            return $raced;
        }

        return $assignment;
    }

    /** @param array<string, mixed> $attributes */
    public function update(Person $person, TitleAssignment $assignment, array $attributes): TitleAssignment
    {
        return $person->getConnection()->transaction(function () use ($person, $assignment, $attributes): TitleAssignment {
            $record = $this->lockAssignment($person, $assignment);
            $reference = $attributes['title_id'] ?? $record->title_id;
            unset($attributes['title_id']);

            if (! is_string($reference) || mb_trim($reference) === '') {
                throw new InvalidArgumentException('A valid assignment reference is required.');
            }

            $record->fill($this->validate($attributes));
            $record->title_id = $reference;
            $this->assertDates($record);

            if (! $record->save()) {
                throw new RuntimeException('Assignment update was cancelled.');
            }

            return $record;
        });
    }

    public function delete(Person $person, TitleAssignment $assignment): bool
    {
        return $person->getConnection()->transaction(function () use ($person, $assignment): bool {
            return (bool) $this->lockAssignment($person, $assignment)->delete();
        });
    }

    private function lockAssignment(Person $person, TitleAssignment $assignment): TitleAssignment
    {
        if (! $person->exists || $person->getKey() === null || ! $assignment->exists || $assignment->getKey() === null) {
            throw new InvalidArgumentException('Person and assignment must be persisted.');
        }

        $person->newQuery()->whereKey($person->getKey())->lockForUpdate()->firstOrFail();

        $record = TitleAssignment::query()
            ->where('titleable_type', $person->getMorphClass())
            ->where('titleable_id', $person->getKey())
            ->whereKey($assignment->getKey())
            ->lockForUpdate()
            ->first();

        if ($record === null) {
            throw new AuthorizationException('The assignment does not belong to this person.');
        }

        return $record;
    }

    private function assertDates(TitleAssignment $assignment): void
    {
        if ($assignment->date_awarded !== null && $assignment->date_expired !== null
            && $assignment->date_expired->lt($assignment->date_awarded)) {
            throw new InvalidArgumentException('The expiry date must be on or after the start date.');
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function validate(array $attributes): array
    {
        $allowed = ['status', 'issuer_id', 'date_awarded', 'date_expired'];
        $unknown = array_diff(array_keys($attributes), $allowed);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown title assignment attributes: ' . implode(', ', $unknown) . '.');
        }

        $status = $attributes['status'] ?? AssignmentStatus::Active;

        if (! $status instanceof AssignmentStatus && AssignmentStatus::tryFrom((string) $status) === null) {
            throw new InvalidArgumentException('Title assignment status must be a valid assignment status value.');
        }

        foreach (['date_awarded', 'date_expired'] as $key) {
            $value = $attributes[$key] ?? null;

            if ($value !== null && ! $value instanceof DateTimeInterface && ! is_string($value)) {
                throw new InvalidArgumentException("Title assignment {$key} must be a date string or DateTime instance.");
            }
        }

        $issuerId = $attributes['issuer_id'] ?? null;

        if ($issuerId !== null && (! is_string($issuerId) || mb_trim($issuerId) === '')) {
            throw new InvalidArgumentException('Title assignment issuer id must be a non-empty string.');
        }

        return $attributes;
    }
}
