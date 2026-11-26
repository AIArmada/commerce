<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

use Carbon\CarbonImmutable;
use SensitiveParameter;

final readonly class IssuedIdentityProof
{
    public function __construct(public string $id, #[SensitiveParameter] public string $plaintextSecret, public ?CarbonImmutable $expiresAt) {}
}
