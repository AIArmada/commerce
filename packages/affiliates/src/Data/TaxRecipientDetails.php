<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

final readonly class TaxRecipientDetails
{
    public function __construct(public string $legalName, #[\SensitiveParameter] public string $tin, public string $address = '') {}
}
