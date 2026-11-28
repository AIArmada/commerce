<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Api;

use AIArmada\Engagement\Contracts\Bookmarkable;
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\Followable;
use AIArmada\Engagement\Contracts\Respondable;
use AIArmada\Engagement\Contracts\Shareable;

interface WritesInteractionRecords
{
    /** @return array<string, mixed> */
    public function recordFollow(CanInteract $actor, Followable $subject, array $options = []): array;

    /** @return array<string, mixed> */
    public function recordBookmark(CanInteract $actor, Bookmarkable $subject, array $options = []): array;

    /** @return array<string, mixed> */
    public function recordResponse(CanInteract $actor, Respondable $subject, string $responseType, array $options = []): array;

    /** @return array<string, mixed> */
    public function recordShare(CanInteract $actor, Shareable $subject, array $options = []): array;
}
