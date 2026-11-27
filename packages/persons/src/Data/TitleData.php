<?php

declare(strict_types=1);

namespace AIArmada\Persons\Data;

use JsonSerializable;

final readonly class TitleData implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $category_id,
        public string $name,
        public ?string $short_form,
        public string $usage_position,
        public int $sort_order,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'short_form' => $this->short_form,
            'usage_position' => $this->usage_position,
            'sort_order' => $this->sort_order,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
