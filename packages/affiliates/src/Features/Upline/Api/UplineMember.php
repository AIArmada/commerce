<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Api;

final readonly class UplineMember
{
    public function __construct(public string $id, public string $name, public string $code, public int $depth) {}
}
