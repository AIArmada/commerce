<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Titles\Queries;

use AIArmada\Persons\Data\TitleAssignmentData;
use AIArmada\Persons\Data\TitleData;
use AIArmada\Persons\Features\Titles\Models\Title;
use AIArmada\Persons\Features\Titles\Models\TitleAssignment;

final readonly class TitleDataMapper
{
    public function fromTitleAssignment(TitleAssignment $assignment): TitleAssignmentData
    {
        return new TitleAssignmentData(
            id: $assignment->id,
            title_id: $assignment->title_id,
            status: $assignment->status->value,
            titleable_type: $assignment->titleable_type,
            titleable_id: $assignment->titleable_id,
            title: $assignment->relationLoaded('title') && $assignment->title
                ? $this->fromTitle($assignment->title)
                : null,
        );
    }

    public function fromTitle(Title $title): TitleData
    {
        return new TitleData(
            id: $title->id,
            category_id: $title->category_id,
            name: $title->name,
            short_form: $title->short_form,
            usage_position: $title->usage_position->value,
            sort_order: $title->sort_order,
        );
    }
}
