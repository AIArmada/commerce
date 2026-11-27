<?php

declare(strict_types=1);

namespace AIArmada\Persons\Data;

final readonly class CredentialDefinitionData implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public string | null $short_form,
        public string | null $field,
        public string $credential_type,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_form' => $this->short_form,
            'field' => $this->field,
            'credential_type' => $this->credential_type,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
