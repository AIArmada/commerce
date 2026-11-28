<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class RewardPointInput
{
    public static function assertRecipient(string $recipientType, string $recipientId): void
    {
        $allowed = RewardLedgerEntries::recipientTypes();

        if (! in_array($recipientType, $allowed, true)) {
            throw new InvalidArgumentException('Recipient type must be one of [' . implode(',', $allowed) . ']; no unclaimed-guest rewards.');
        }

        if (! Str::isUuid($recipientId)) {
            throw new InvalidArgumentException('Recipient id must be a UUID.');
        }
    }

    public static function assertFamily(string $familyKey): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9_\-]{0,63}$/', $familyKey) !== 1) {
            throw new InvalidArgumentException('Family key must be a 1..64 slug.');
        }
    }

    public static function assertUnit(string $unit): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_\-]{0,15}$/', $unit) !== 1) {
            throw new InvalidArgumentException('Unit must be 1..16 alphanumerics.');
        }
    }

    public static function assertActor(string $actorType, ?string $actorId): void
    {
        if (! in_array($actorType, ['user', 'system'], true)) {
            throw new InvalidArgumentException('Actor type must be [user] or [system].');
        }

        if ($actorType === 'system' && $actorId !== null) {
            throw new InvalidArgumentException('System actors must not carry an actor id.');
        }

        if ($actorType === 'user' && ($actorId === null || ! Str::isUuid($actorId))) {
            throw new InvalidArgumentException('User actors require an explicit UUID actor id.');
        }
    }

    public static function assertReason(string $reason): void
    {
        if ($reason === '' || mb_strlen($reason) > 191) {
            throw new InvalidArgumentException('Reason must be a non-empty string up to 191 characters.');
        }
    }
}
