<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

interface CommunicationReferenceWriter
{
    /** @param array<string, mixed>|null $metadata */
    public function handle(
        string $communicationId,
        string $referenceType,
        string $referenceId,
        ?string $role = null,
        ?array $metadata = null,
    ): string;
}
