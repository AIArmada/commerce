<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

use AIArmada\Docs\Data\IssuedShareLink;
use AIArmada\Docs\Data\ShareLinkData;

interface DocumentSharing
{
    public function issue(string $docId, ShareLinkData $data): IssuedShareLink;

    public function revokeAll(string $docId): void;
}
