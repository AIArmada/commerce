<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

interface DocumentEmailSender
{
    /** @param array<string, mixed> $metadata */
    public function sendTo(
        string $docId,
        string $recipientEmail,
        ?string $recipientName = null,
        ?string $templateId = null,
        ?string $subject = null,
        ?string $body = null,
        array $metadata = [],
    ): string;
}
