<?php

declare(strict_types=1);

namespace AIArmada\Persons\Data;

use AIArmada\Persons\Models\Title;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class TitleData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $category_id,
        public readonly string $name,
        public readonly string | null | Optional $short_form,
        public readonly string $usage_position,
        public readonly int $sort_order,
    ) {}

    public static function fromTitle(Title $title): self
    {
        return new self(
            id: $title->id,
            category_id: $title->category_id,
            name: $title->name,
            short_form: $title->short_form,
            usage_position: $title->usage_position->value,
            sort_order: $title->sort_order,
        );
    }
}
