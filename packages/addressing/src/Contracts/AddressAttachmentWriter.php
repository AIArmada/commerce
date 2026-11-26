<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Contracts;

use AIArmada\Addressing\Models\Addressable;

interface AddressAttachmentWriter
{
    /** @param array{type: string, label?: string|null, is_primary: bool} $data */
    public function execute(Addressable $pivot, array $data): Addressable;

    public function detach(Addressable $pivot): void;

    public function attach(string $addressId, string $addressableType, string $addressableId, string $type = 'primary', bool $isPrimary = false, ?string $label = null): string;

    public function setPrimary(string $addressId, string $addressableType, string $addressableId, string $type = 'primary'): string;
}
