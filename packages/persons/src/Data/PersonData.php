<?php

declare(strict_types=1);

namespace AIArmada\Persons\Data;

use JsonSerializable;

final readonly class PersonData implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $family_name,
        public ?string $middle_name,
        public ?string $gender,
        public ?string $nationality_country_id,
        public ?string $slug,
        public ?string $status,
        public string $formatted_name,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'family_name' => $this->family_name,
            'middle_name' => $this->middle_name,
            'gender' => $this->gender,
            'nationality_country_id' => $this->nationality_country_id,
            'slug' => $this->slug,
            'status' => $this->status,
            'formatted_name' => $this->formatted_name,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
