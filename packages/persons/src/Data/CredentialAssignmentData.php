<?php

declare(strict_types=1);

namespace AIArmada\Persons\Data;

use JsonSerializable;

final readonly class CredentialAssignmentData implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $credential_id,
        public string $status,
        public ?string $registration_number,
        public ?string $date_obtained,
        public ?CredentialDefinitionData $credential,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'credential_id' => $this->credential_id,
            'status' => $this->status,
            'registration_number' => $this->registration_number,
            'date_obtained' => $this->date_obtained,
            'credential' => $this->credential?->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
