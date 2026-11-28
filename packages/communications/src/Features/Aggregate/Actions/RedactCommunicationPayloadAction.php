<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationContent;

final class RedactCommunicationPayloadAction
{
    public function __construct(
        private readonly PayloadRedactor $redactor,
    ) {}

    public function handleContent(string $contentId): CommunicationContent
    {
        $content = CommunicationContent::query()->findOrFail($contentId);

        if (CommunicationContent::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(CommunicationContent::class, $contentId);
        }

        if ($content->payload !== null) {
            $content->payload = $this->redactor->redact($content->payload);
        }

        $content->save();

        return $content;
    }
}
