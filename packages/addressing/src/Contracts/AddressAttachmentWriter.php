<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Contracts;

use AIArmada\Addressing\Models\Addressable;

interface AddressAttachmentWriter
{
    /** @param array{type: string, label?: string|null, is_primary: bool} $data */
    public function execute(Addressable $pivot, array $data): Addressable;

    public function detach(Addressable $pivot): void;
}
