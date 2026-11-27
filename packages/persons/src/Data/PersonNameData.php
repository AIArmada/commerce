<?php

declare(strict_types=1);

namespace AIArmada\Persons\Data;

use JsonSerializable;

final readonly class PersonNameData implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $person_id,
        public string $name_type,
        public string $full_name,
        public string $language_code,
        public bool $is_primary,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'person_id' => $this->person_id,
            'name_type' => $this->name_type,
            'full_name' => $this->full_name,
            'language_code' => $this->language_code,
            'is_primary' => $this->is_primary,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
