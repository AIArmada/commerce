<?php

declare(strict_types=1);

namespace AIArmada\Persons\Data;

use JsonSerializable;

final readonly class AffiliationRoleData implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $role_name,
        public ?string $department,
        public bool $is_current,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'role_name' => $this->role_name,
            'department' => $this->department,
            'is_current' => $this->is_current,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
