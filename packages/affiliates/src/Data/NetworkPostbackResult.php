<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

final readonly class NetworkPostbackResult
{
    /** @param array{winner: string, reason: string}|null $decision */
    public function __construct(public bool $ok, public ?int $status = null, public ?array $decision = null, public ?string $legStatus = null, public ?string $error = null) {}
}
