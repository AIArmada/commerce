<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Awards;

use InvalidArgumentException;

/**
 * Caller-owned bounded stable award semantic key.
 *
 * The caller supplies a namespaced identity for the business operation
 * (for example `acme:award:2026-10-07:9f2c`). The package never interprets
 * the namespace structure — no delimiter coupling — and hashes the exact
 * validated identity (`sha256`) for the unique `idempotency_key`, so
 * canonical uniqueness is independent of recipient, owner, or link.
 * Replays key on that same hash and preserve the frozen original row.
 */
final readonly class AffiliateAwardKey
{
    public const MAX_LENGTH = 191;

    public string $value;

    public function __construct(string $value)
    {
        $value = mb_trim($value);

        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH || str_contains($value, "\0")) {
            throw new InvalidArgumentException('Award key must be a non-empty caller-owned namespaced identity of at most 191 characters.');
        }

        $this->value = $value;
    }

    public static function parse(string $value): self
    {
        return new self($value);
    }

    public function idempotencyKey(): string
    {
        return hash('sha256', $this->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
