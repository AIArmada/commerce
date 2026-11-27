<?php

declare(strict_types=1);

namespace AIArmada\Persons\Data;

use JsonSerializable;

final readonly class AffiliationData implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $affiliation_type,
        public ?string $institution_id,
        public bool $is_primary,
        public array $roles,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'affiliation_type' => $this->affiliation_type,
            'institution_id' => $this->institution_id,
            'is_primary' => $this->is_primary,
            'roles' => $this->roles,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
