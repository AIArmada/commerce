<?php

declare(strict_types=1);

namespace AIArmada\Persons\Actions;

use AIArmada\Persons\Enums\AssignmentStatus;
use AIArmada\Persons\Models\Title;
use AIArmada\Persons\Models\TitleAssignment;
use Illuminate\Database\Eloquent\Model;
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
            'status' => $attributes['status'] ?? AssignmentStatus::Active,
            ...$attributes,
        ]);
        $assignment->title_id = $title->getKey();
        $assignment->titleable()->associate($titleable);
        $assignment->save();

        return $assignment;
    }
}
