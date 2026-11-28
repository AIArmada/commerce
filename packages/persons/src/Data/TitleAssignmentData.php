<?php

declare(strict_types=1);

namespace AIArmada\Persons\Data;

use JsonSerializable;

final readonly class TitleAssignmentData implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $title_id,
        public string $status,
        public ?string $titleable_type,
        public ?string $titleable_id,
        public ?TitleData $title,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title_id' => $this->title_id,
            'status' => $this->status,
            'titleable_type' => $this->titleable_type,
            'titleable_id' => $this->titleable_id,
            'title' => $this->title?->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
