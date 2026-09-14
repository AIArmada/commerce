<?php

declare(strict_types=1);

namespace AIArmada\Persons\Actions;

use AIArmada\Persons\Enums\AssignmentStatus;
use AIArmada\Persons\Models\Title;
use AIArmada\Persons\Models\TitleAssignment;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

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

        try {
            $assignment->save();
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

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
