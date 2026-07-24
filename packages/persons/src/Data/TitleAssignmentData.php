<?php

declare(strict_types=1);

namespace AIArmada\Persons\Data;

use AIArmada\Persons\Models\TitleAssignment;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class TitleAssignmentData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $title_id,
        public readonly string $status,
        public readonly string | null | Optional $titleable_type,
        public readonly string | null | Optional $titleable_id,
        public readonly TitleData | null | Optional $title,
    ) {}

    public static function fromTitleAssignment(TitleAssignment $assignment): self
    {
        return new self(
            id: $assignment->id,
            title_id: $assignment->title_id,
            status: $assignment->status->value,
            titleable_type: $assignment->titleable_type,
            titleable_id: $assignment->titleable_id,
            title: $assignment->relationLoaded('title') && $assignment->title
                ? TitleData::fromTitle($assignment->title)
                : null,
        );
    }
}
