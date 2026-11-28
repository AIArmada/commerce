<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Titles\Queries;

use AIArmada\Persons\Enums\AssignmentStatus;
use AIArmada\Persons\Enums\TitleUsagePosition;
use AIArmada\Persons\Features\Titles\Models\Title;
use AIArmada\Persons\Features\Titles\Models\TitleAssignment;
use AIArmada\Persons\Models\Person;
use AIArmada\Persons\Runtime\PersonTitleFormatting;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final readonly class FormatPersonTitleName implements PersonTitleFormatting
{
    public function format(Person $person): string
    {
        if ($person->relationLoaded('titleAssignments')) {
            /** @var EloquentCollection<int, TitleAssignment> $loadedAssignments */
            $loadedAssignments = $person->getRelation('titleAssignments');

            $hasCompleteTitleGraph = ! $loadedAssignments->contains(
                static fn (TitleAssignment $assignment): bool => ! $assignment->relationLoaded('title')
                    || (($title = $assignment->getRelationValue('title')) instanceof Title
                        && ! $title->relationLoaded('category')),
            );

            if ($hasCompleteTitleGraph) {
                $assignments = $loadedAssignments;
            } else {
                $assignments = $person->titleAssignments()
                    ->where('status', AssignmentStatus::Active)
                    ->with('title.category')
                    ->get();
            }
        } else {
            $assignments = $person->titleAssignments()
                ->where('status', AssignmentStatus::Active)
                ->with('title.category')
                ->get();
        }

        /** @var EloquentCollection<int, TitleAssignment> $assignments */
        $assignments = $assignments
            ->filter(fn (TitleAssignment $assignment): bool => $assignment->status === AssignmentStatus::Active
                && $assignment->title !== null
                && $assignment->title->category !== null)
            ->values();

        $before = $assignments
            ->filter(fn (TitleAssignment $a) => $a->title->usage_position === TitleUsagePosition::BeforeName)
            ->sort($this->compareTitleAssignments(...))
            ->map(fn (TitleAssignment $a) => $a->title->short_form ?? $a->title->name);

        $after = $assignments
            ->filter(fn (TitleAssignment $a) => $a->title->usage_position === TitleUsagePosition::AfterName)
            ->sort($this->compareTitleAssignments(...))
            ->map(fn (TitleAssignment $a) => $a->title->short_form ?? $a->title->name);

        $name = mb_trim(implode(' ', $before->all()) . ' ' . $person->name);

        if ($after->isNotEmpty()) {
            $name .= ', ' . implode(', ', $after->all());
        }

        return mb_trim($name);
    }

    private function compareTitleAssignments(TitleAssignment $left, TitleAssignment $right): int
    {
        $categoryOrder = $left->title->category->sort_order <=> $right->title->category->sort_order;

        if ($categoryOrder !== 0) {
            return $categoryOrder;
        }

        $titleOrder = $left->title->sort_order <=> $right->title->sort_order;

        return $titleOrder !== 0
            ? $titleOrder
            : ((string) $left->title->getKey() <=> (string) $right->title->getKey());
    }
}
